/**
 * Room create/edit form (resources/views/studio/rooms/partials/_form.blade.php).
 *
 * cfg: {
 *   courses: [{id, category_id, title}], topics: [{id, course_id, title}],
 *   categoryId, courseId, topicId, access,
 *   locked: bool,                 // live room: only title/description/toggles may change
 *   today: 'YYYY-MM-DD', nowTime: 'HH:MM',            // "now" in the app timezone (server clock)
 *   originalDate, originalTime,   // the room's saved start (edit), may lie in the past
 *   date, time                    // initial field values
 * }
 *
 * Markup contract:
 *   <select name="learning_category_id" x-model="categoryId">   options rendered server-side
 *   <select name="learning_course_id" x-model="courseId">       options from filteredCourses
 *   <select name="learning_topic_id" x-model="topicId">         options from filteredTopics
 *   <input type="radio" name="access" x-model="access">
 *   <input type="date" name="scheduled_date" x-model="date" :min="dateMin">
 *   <input type="time" name="scheduled_time" x-model="time">
 *   buttons: <button type="submit" name="action" value="schedule" data-schedule>
 * Choosing a course forces its category (the server does the same); changing the category clears a
 * course from another category; changing the course clears a topic from another course.
 */
const str = (v) => (v === null || v === undefined ? '' : String(v));

const ACCESS_HINTS = {
    public: 'Every signed-in member can see the room and join while it is live.',
    private: 'Only the members you invite below (and room staff) can see and join the room.',
    category: 'Learners enrolled in any course of the chosen category can join.',
    course: 'Only learners enrolled in the chosen course can join.',
};

window.learnRoomForm = (cfg = {}) => ({
    courses: Array.isArray(cfg.courses) ? cfg.courses : [],
    topics: Array.isArray(cfg.topics) ? cfg.topics : [],
    categoryId: str(cfg.categoryId),
    courseId: str(cfg.courseId),
    topicId: str(cfg.topicId),
    access: str(cfg.access) || 'public',
    locked: !!cfg.locked,
    today: str(cfg.today),
    nowTime: str(cfg.nowTime),
    originalDate: str(cfg.originalDate),
    originalTime: str(cfg.originalTime),
    date: str(cfg.date),
    time: str(cfg.time),
    scheduleError: '',

    init() {
        const initial = { category: this.categoryId, course: this.courseId, topic: this.topicId };

        if (this.courseId) {
            const course = this.findCourse(this.courseId);
            if (course) this.categoryId = str(course.category_id);
        }

        // Options rendered with x-for appear after x-model first runs: re-apply the initial values.
        this.$nextTick(() => {
            this.categoryId = this.categoryId || initial.category;
            this.courseId = initial.course;
            this.topicId = initial.topic;
        });

        this.$watch('courseId', (value) => {
            const course = this.findCourse(value);
            if (course) this.categoryId = str(course.category_id);
            if (this.topicId && !this.filteredTopics.some((t) => str(t.id) === this.topicId)) this.topicId = '';
        });

        this.$watch('categoryId', (value) => {
            const course = this.findCourse(this.courseId);
            if (course && str(course.category_id) !== str(value)) {
                this.courseId = '';
                this.topicId = '';
            }
        });

        this.$watch('date', () => { this.scheduleError = ''; });
        this.$watch('time', () => { this.scheduleError = ''; });

        // Client-side guard for the "Schedule" button (the server checks again).
        const form = this.$el.closest('form') || this.$el;
        form.addEventListener('submit', (e) => {
            if (this.locked) return;
            const submitter = e.submitter;
            if (!submitter || !submitter.hasAttribute('data-schedule')) return;
            if (!this.date || !this.time) {
                e.preventDefault();
                this.scheduleError = 'Pick a date and start time to schedule the room.';
                this.$refs.date?.focus();
            } else if (this.isPast) {
                e.preventDefault();
                this.scheduleError = 'That start time has already passed — pick a time from now on.';
                this.$refs.time?.focus();
            }
        });
    },

    findCourse(id) {
        return id ? this.courses.find((c) => str(c.id) === str(id)) ?? null : null;
    },

    get filteredCourses() {
        return this.categoryId
            ? this.courses.filter((c) => str(c.category_id) === this.categoryId)
            : this.courses;
    },

    get filteredTopics() {
        return this.courseId ? this.topics.filter((t) => str(t.course_id) === this.courseId) : [];
    },

    get courseForcesCategory() {
        return !!this.findCourse(this.courseId);
    },

    // --- Access -----------------------------------------------------------
    get accessHint() {
        return ACCESS_HINTS[this.access] || '';
    },

    get showMembers() {
        return this.access === 'private';
    },

    get needsCourse() {
        return this.access === 'course' && !this.courseId;
    },

    get needsCategory() {
        return this.access === 'category' && !this.categoryId && !this.courseId;
    },

    // --- Date & time ------------------------------------------------------
    /** Today (app timezone), unless the saved date already lies in the past and is kept. */
    get dateMin() {
        if (this.locked) return null;
        if (this.originalDate && this.originalDate < this.today && this.date === this.originalDate) return null;
        return this.today || null;
    },

    get unchangedStart() {
        return this.date === this.originalDate && this.time.slice(0, 5) === this.originalTime;
    },

    get isPast() {
        if (!this.date || !this.time || !this.today) return false;
        const t = this.time.slice(0, 5);
        return this.date < this.today || (this.date === this.today && t < this.nowTime);
    },

    /** Warn about a past start unless it is the (untouched) saved one. */
    get pastWarning() {
        return !this.locked && this.isPast && !this.unchangedStart;
    },
});
