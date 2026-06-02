// AMD module: detail modal for local_tituscontentlibrary.
import Modal from 'core/modal';
import Templates from 'core/templates';
import TileController from 'local_tituscontentlibrary/tile';

/**
 * Render and display the detail modal for a catalogue item.
 *
 * The modal's Add-to-Moodle button reuses the existing tile FSM via TileController.
 *
 * @param {Object}  item           Hydrated catalogue item (same shape as state.items).
 * @param {Element} triggerElement DOM element that triggered the modal (for focus return).
 * @param {Function} announce      Announcement helper from the marketplace controller.
 * @param {Object}  Repository     Repository module reference.
 */
export const show = async(item, triggerElement, announce, Repository) => {
    const {html, js} = await Templates.renderForPromise(
        'local_tituscontentlibrary/detail_modal',
        item
    );

    const modal = await Modal.create({
        title:         item.title,
        body:          html,
        large:         true,
        removeOnClose: true,
        returnElement: triggerElement,
    });

    await Templates.runTemplateJS(js);
    modal.show();

    // Wire the Add button inside the modal body to the tile FSM.
    modal.getRoot()[0].addEventListener('click', async(e) => {
        const addBtn = e.target.closest('[data-action="add"]');
        if (!addBtn) {
            return;
        }
        // Find the corresponding tile in the marketplace grid (if still visible).
        const contentid = addBtn.dataset.contentId;
        const tile = document.querySelector(
            `[data-region="titus-tile"][data-content-id="${CSS.escape(contentid)}"]`
        );
        if (tile) {
            await TileController.handleAdd(tile, announce, Repository);
        }
        // Close modal after queuing.
        modal.hide();
    });
};
