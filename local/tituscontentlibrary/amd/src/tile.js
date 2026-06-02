// AMD module: tile state machine for local_tituscontentlibrary.
// States: IDLE → QUEUEING → QUEUED → PROCESSING → COMPLETED | FAILED
import Templates from 'core/templates';
import Pending from 'core/pending';
import {get_string as getString} from 'core/str';
import Toast from 'core/toast';

const STATES = {
    IDLE:       'IDLE',
    QUEUEING:   'QUEUEING',
    QUEUED:     'QUEUED',
    PROCESSING: 'PROCESSING',
    COMPLETED:  'COMPLETED',
    FAILED:     'FAILED',
};

// Exponential backoff delays in ms, capped at 30 s.
const POLL_DELAYS = [3000, 3000, 5000, 8000, 13000, 21000, 30000];
const TIMEOUT_MS  = 60000;

// Module-level map of active poll timers keyed by contentid.
const activePolls = new Map();

window.addEventListener('beforeunload', () => {
    activePolls.forEach(rec => clearTimeout(rec.timerId));
    activePolls.clear();
});

/**
 * Re-render the tile's action area with the given state context.
 *
 * @param {Element} tile
 * @param {string}  state   One of the STATES constants.
 * @param {Object}  extra   {errormessage, courseurl}
 * @returns {Promise<Element>} The freshly rendered tile element (replaces old one in DOM).
 */
const setState = async(tile, state, extra = {}) => {
    const contentid = tile.dataset.contentId;
    const ctx = {
        id:               contentid,
        title:            tile.querySelector('.card-title')?.textContent?.trim() || '',
        shortdescription: tile.querySelector('.card-text')?.textContent?.trim() || '',
        thumbnailurl:     tile.querySelector('img')?.getAttribute('src') || '',
        category:         tile.dataset.categoryId || '',
        durationminutes:  parseInt(tile.dataset.durationMinutes || '0', 10),
        isfeatured:       tile.dataset.isfeatured === '1',
        isnew:            tile.dataset.isnew === '1',
        isadded:          state === STATES.COMPLETED,
        isloading:        state === STATES.QUEUEING || state === STATES.PROCESSING,
        isqueued:         state === STATES.QUEUED,
        haserror:         state === STATES.FAILED,
        errormessage:     extra.errormessage || '',
        courseurl:        extra.courseurl    || '',
    };
    const {html, js} = await Templates.renderForPromise('local_tituscontentlibrary/tile', ctx);
    const newTiles = await Templates.replaceNode(tile, html, js);
    // replaceNode returns the new node(s); preserve the processing guard.
    if (newTiles && newTiles.length) {
        const newTile = newTiles[0];
        newTile.dataset.processing = tile.dataset.processing || '';
        return newTile;
    }
    return tile;
};

/**
 * Classify an API/network error into a user-facing message string.
 *
 * @param {Object|Error} err
 * @returns {Promise<string>}
 */
const categorizeError = async(err) => {
    if (!err) {
        return '';
    }
    if (err.errorcode === 'servererror' || (err.status >= 500 && err.status < 600)) {
        return getString('error:server', 'local_tituscontentlibrary');
    }
    if (err.message && err.message.toLowerCase().includes('network')) {
        return getString('error:network', 'local_tituscontentlibrary');
    }
    return Promise.resolve(err.message || String(err));
};

/**
 * Start the polling loop for a tile after it has been queued.
 *
 * @param {Element}  tile
 * @param {string}   contentid
 * @param {string}   title
 * @param {Function} announce  Accessibility announcement helper.
 * @param {Object}   repository
 */
const startPolling = (tile, contentid, title, announce, repository) => {
    const startedAt = Date.now();
    const rec = {timerId: null, attempts: 0, startedAt};
    activePolls.set(contentid, rec);

    const poll = async() => {
        if (Date.now() - startedAt > TIMEOUT_MS) {
            activePolls.delete(contentid);
            tile.dataset.processing = '';
            const msg = await getString('error:timeout', 'local_tituscontentlibrary');
            await setState(tile, STATES.FAILED, {errormessage: msg});
            announce('announce:failed', title);
            return;
        }

        // Skip poll when tab is backgrounded to reduce server load.
        if (document.visibilityState !== 'visible') {
            rec.timerId = setTimeout(poll, 3000);
            return;
        }

        const pending = new Pending('local_tituscontentlibrary/tile:poll-' + contentid);
        try {
            const status = await repository.getAddStatus(contentid);

            if (status.status === 'completed') {
                activePolls.delete(contentid);
                tile.dataset.processing = '';
                tile = await setState(tile, STATES.COMPLETED, {courseurl: status.course_url});
                announce('announce:completed', title);
                const successMsg = await getString('toast:added', 'local_tituscontentlibrary');
                Toast.add(successMsg, {type: 'success'});

            } else if (status.status === 'failed') {
                activePolls.delete(contentid);
                tile.dataset.processing = '';
                tile = await setState(tile, STATES.FAILED, {errormessage: status.errormessage || ''});
                announce('announce:failed', title);

            } else {
                if (status.status === 'processing') {
                    tile = await setState(tile, STATES.PROCESSING);
                    announce('announce:processing', title);
                }
                rec.attempts += 1;
                const delay = POLL_DELAYS[Math.min(rec.attempts, POLL_DELAYS.length - 1)];
                rec.timerId = setTimeout(poll, delay);
            }
        } catch(err) {
            activePolls.delete(contentid);
            tile.dataset.processing = '';
            const msg = await categorizeError(err);
            tile = await setState(tile, STATES.FAILED, {errormessage: msg});
            announce('announce:failed', title);
        } finally {
            pending.resolve();
        }
    };

    rec.timerId = setTimeout(poll, POLL_DELAYS[0]);
};

/**
 * Handle the "Add to Moodle" action for a tile.
 *
 * @param {Element}  tile
 * @param {Function} announce    Accessibility announcement helper from marketplace.js.
 * @param {Object}   repository  WS repository module.
 */
const handleAdd = async(tile, announce, repository) => {
    if (!tile || tile.dataset.processing === '1') {
        return; // anti double-click guard
    }
    tile.dataset.processing = '1';

    const contentid = tile.dataset.contentId;
    const title     = tile.querySelector('.card-title')?.textContent?.trim() || contentid;

    const pending = new Pending('local_tituscontentlibrary/tile:add-' + contentid);
    try {
        tile = await setState(tile, STATES.QUEUEING);
        announce('announce:queueing', title);

        const result = await repository.addCourse(contentid);

        if (result.status === 'completed') {
            tile.dataset.processing = '';
            tile = await setState(tile, STATES.COMPLETED, {courseurl: result.course_url || ''});
            announce('announce:completed', title);
            const successMsg = await getString('toast:added', 'local_tituscontentlibrary');
            Toast.add(successMsg, {type: 'success'});

        } else if (result.status === 'failed') {
            tile.dataset.processing = '';
            tile = await setState(tile, STATES.FAILED, {errormessage: result.errormessage || ''});
            announce('announce:failed', title);

        } else {
            // pending or processing — enter polling loop.
            tile = await setState(tile, STATES.QUEUED);
            announce('announce:queued', title);
            startPolling(tile, contentid, title, announce, repository);
        }
    } catch(err) {
        tile.dataset.processing = '';
        const msg = await categorizeError(err);
        tile = await setState(tile, STATES.FAILED, {errormessage: msg});
        announce('announce:failed', title);
    } finally {
        pending.resolve();
    }
};

/**
 * Cancel all active polling loops (e.g., on SPA navigation).
 */
const cancelAllPolls = () => {
    activePolls.forEach(rec => clearTimeout(rec.timerId));
    activePolls.clear();
};

export default {handleAdd, cancelAllPolls, STATES};
