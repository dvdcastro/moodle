// AMD module: client-side category filter for the Titus marketplace catalogue.

/**
 * Filter catalogue items by category.
 * Pure function — no DOM side effects.
 *
 * @param {Array}  items      Array of tile context objects.
 * @param {string} categoryId Category slug, or '' for all categories.
 * @returns {Array} Filtered subset.
 */
export const filter = (items, categoryId) => {
    if (!categoryId) {
        return items;
    }
    return items.filter(item => {
        const slug = (item.category || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        return slug === categoryId;
    });
};
