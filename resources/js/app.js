import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Learning (ROOM) Alpine factories must exist before Alpine boots.
import './learning';

Alpine.start();

window.submitWithReason = function (form, promptText) {
    const reason = window.prompt(promptText || 'Please give a reason:');
    if (!reason || !reason.trim()) {
        return false;
    }
    form.querySelector('[name="reason"]').value = reason.trim();
    return true;
};
