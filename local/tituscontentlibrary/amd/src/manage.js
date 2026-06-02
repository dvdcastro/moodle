// AMD module: manage page controller for local_tituscontentlibrary.
import * as Repository from 'local_tituscontentlibrary/repository';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

/**
 * Initialise the manage page controller.
 *
 * @param {string} rootSelector CSS selector for the manage region wrapper.
 */
export const init = (rootSelector) => {
    const root = document.querySelector(rootSelector);
    if (!root) {
        return;
    }

    root.addEventListener('click', async(e) => {
        const resyncBtn = e.target.closest('[data-action="resync"]');
        if (!resyncBtn) {
            return;
        }

        const contentid = resyncBtn.dataset.contentId;
        if (!contentid) {
            return;
        }

        const title    = await getString('manage:action:resync',  'local_tituscontentlibrary');
        const question = await getString('manage:confirm:resync', 'local_tituscontentlibrary');

        // Use native confirm for this admin-only page.
        if (!window.confirm(question)) {
            return;
        }

        resyncBtn.disabled = true;
        resyncBtn.textContent = '...';

        try {
            await Repository.resyncCourse(contentid);
            // Reload so the status badge reflects the queued state.
            window.location.reload();
        } catch(err) {
            Notification.exception(err);
            resyncBtn.disabled = false;
            resyncBtn.textContent = title;
        }
    });
};
