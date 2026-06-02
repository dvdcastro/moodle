// AMD module: client-side search filter for the Titus marketplace catalogue.

/**
 * Filter catalogue items by a search query.
 * Pure function — no DOM side effects.
 *
 * @param {Array} items  Array of tile context objects.
 * @param {string} query Search string (case-insensitive).
 * @returns {Array} Filtered subset.
 */
export const filter = (items, query) => {
    if (!query || !query.trim()) {
        return items;
    }
    const lc = query.trim().toLowerCase();
    return items.filter(item =>
        (item.title || '').toLowerCase().includes(lc) ||
        (item.shortdescription || '').toLowerCase().includes(lc) ||
        (item.category || '').toLowerCase().includes(lc) ||
        (item.subcategory || '').toLowerCase().includes(lc) ||
        (item.tags || []).some(t => t.toLowerCase().includes(lc))
    );
};
