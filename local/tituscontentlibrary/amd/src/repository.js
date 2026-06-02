// AMD module: thin WS repository wrapper for local_tituscontentlibrary.
import Ajax from 'core/ajax';

/**
 * Fetch the full cached Titus content catalogue.
 * @returns {Promise<{items: Array, stale: boolean}>}
 */
export const getCatalogue = () =>
    Ajax.call([{methodname: 'local_tituscontentlibrary_get_catalogue', args: {}}])[0];

/**
 * Queue a Titus content item for import as a Moodle course.
 * @param {string} contentid
 * @returns {Promise<{queued: boolean, alreadyqueued: boolean, addedrowid: number, status: string}>}
 */
export const addCourse = (contentid) =>
    Ajax.call([{methodname: 'local_tituscontentlibrary_add_course', args: {contentid}}])[0];

/**
 * Poll the current import status for a content item.
 * @param {string} contentid
 * @returns {Promise<{status: string, course_url: string|null, errormessage: string|null}>}
 */
export const getAddStatus = (contentid) =>
    Ajax.call([{methodname: 'local_tituscontentlibrary_get_add_status', args: {contentid}}])[0];

/**
 * Server-side filter + sort over the cached catalogue.
 * @param {{query?: string, category?: string, sort?: string}} args
 * @returns {Promise<{items: Array, stale: boolean}>}
 */
/**
 * Queue a re-sync of a previously imported Titus SCORM package.
 * @param {string} contentid
 * @returns {Promise<{queued: boolean}>}
 */
export const resyncCourse = (contentid) =>
    Ajax.call([{methodname: 'local_tituscontentlibrary_resync_course', args: {contentid}}])[0];

export const searchCatalogue = ({query = '', category = '', sort = 'az'} = {}) =>
    Ajax.call([{methodname: 'local_tituscontentlibrary_search_catalogue', args: {query, category, sort}}])[0];
