/**
 * Lesson create/edit form: dependent category -> course -> topic selects and tag chips.
 *
 * cfg: {
 *   courses: [{id, category_id, title}], topics: [{id, course_id, title}],
 *   categoryId, courseId, topicId, tags: [string]
 * }
 *
 * Markup contract (resources/views/studio/videos/partials/_form.blade.php):
 *   <select name="learning_category_id" x-model="categoryId">  (options rendered server-side)
 *   <select name="learning_course_id" x-model="courseId">      (options from filteredCourses)
 *   <select name="learning_topic_id" x-model="topicId">        (options from filteredTopics)
 *   <input type="hidden" name="tags" :value="tagsValue">
 * Choosing a course forces its category (the server does the same); changing the category
 * clears a course from another category; changing the course clears a topic from another course.
 */
const MAX_TAGS = 15;
const MAX_TAG_LENGTH = 40;

const str = (v) => (v === null || v === undefined ? '' : String(v));

window.learnVideoForm = (cfg = {}) => ({
    courses: Array.isArray(cfg.courses) ? cfg.courses : [],
    topics: Array.isArray(cfg.topics) ? cfg.topics : [],
    categoryId: str(cfg.categoryId),
    courseId: str(cfg.courseId),
    topicId: str(cfg.topicId),
    tags: Array.isArray(cfg.tags) ? cfg.tags.map(String).filter(Boolean).slice(0, MAX_TAGS) : [],
    tagInput: '',
    tagError: '',

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

    // --- Tags ------------------------------------------------------------
    get tagsValue() {
        return this.tags.join(', ');
    },

    addTag(raw = null) {
        const parts = str(raw ?? this.tagInput).split(',');
        this.tagError = '';
        for (let part of parts) {
            part = part.replace(/\s+/g, ' ').trim();
            if (!part) continue;
            if (part.length > MAX_TAG_LENGTH) {
                this.tagError = `Tags can be at most ${MAX_TAG_LENGTH} characters.`;
                part = part.slice(0, MAX_TAG_LENGTH).trim();
            }
            if (this.tags.some((t) => t.toLowerCase() === part.toLowerCase())) continue;
            if (this.tags.length >= MAX_TAGS) {
                this.tagError = `You can add up to ${MAX_TAGS} tags.`;
                break;
            }
            this.tags.push(part);
        }
        this.tagInput = '';
    },

    removeTag(index) {
        this.tags.splice(index, 1);
        this.tagError = '';
    },

    onTagKeydown(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // never submit the whole form from the tag box
            this.addTag();
        } else if (e.key === ',' || e.key === 'Tab') {
            if (this.tagInput.trim() === '') return; // let Tab move focus
            e.preventDefault();
            this.addTag();
        } else if (e.key === 'Backspace' && this.tagInput === '' && this.tags.length) {
            this.tags.pop();
        }
    },

    onTagPaste(e) {
        const text = e.clipboardData?.getData('text') ?? '';
        if (text.includes(',')) {
            e.preventDefault();
            this.addTag(this.tagInput + text);
        }
    },
});
