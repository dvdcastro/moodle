// AMD module: marketplace controller for local_tituscontentlibrary.
import * as Repository from 'local_tituscontentlibrary/repository';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {prefetchStrings, prefetchTemplates} from 'core/prefetch';
import {get_string as getString} from 'core/str';
import Selectors from 'local_tituscontentlibrary/selectors';
import TileController from 'local_tituscontentlibrary/tile';
import * as DetailModal from 'local_tituscontentlibrary/detail_modal';
import {filter as searchFilter} from 'local_tituscontentlibrary/search';
import {filter as categoryFilter} from 'local_tituscontentlibrary/category_filter';

const DEBOUNCE_MS = 300;

/**
 * Sort an items array by the given sort key (client-side mirror of catalogue_sort.php).
 *
 * @param {Array}  items
 * @param {string} sort
 * @returns {Array}
 */
const sortItems = (items, sort) => {
    const sorted = [...items];
    switch (sort) {
        case 'za':
            sorted.sort((a, b) => (b.title || '').localeCompare(a.title || ''));
            break;
        case 'newest':
            sorted.sort((a, b) => ((b.publishedat || 0) - (a.publishedat || 0)));
            break;
        case 'duration':
            sorted.sort((a, b) => (a.durationminutes || 0) - (b.durationminutes || 0));
            break;
        case 'category':
            sorted.sort((a, b) => (a.category || '').localeCompare(b.category || ''));
            break;
        case 'featured':
            sorted.sort((a, b) => {
                if (a.isfeatured !== b.isfeatured) {
                    return b.isfeatured ? 1 : -1;
                }
                return (a.title || '').localeCompare(b.title || '');
            });
            break;
        case 'new':
            sorted.sort((a, b) => {
                if (a.isnew !== b.isnew) {
                    return b.isnew ? 1 : -1;
                }
                return (a.title || '').localeCompare(b.title || '');
            });
            break;
        default: // 'az'
            sorted.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
    }
    return sorted;
};

/**
 * Build tile context from a DOM tile node (for re-hydration without a WS call).
 *
 * @param {Element} tileNode
 * @returns {Object}
 */
const hydrateFromDom = (tileNode) => ({
    id:               tileNode.dataset.contentId || '',
    title:            tileNode.querySelector('.card-title')?.textContent?.trim() || '',
    shortdescription: tileNode.querySelector('.card-text')?.textContent?.trim() || '',
    description:      tileNode.dataset.description || '',
    version:          tileNode.dataset.version || '',
    updatedat:        tileNode.dataset.updatedat || '',
    thumbnailurl:     tileNode.querySelector('img')?.getAttribute('src') || '',
    category:         tileNode.dataset.categoryId || '',
    durationminutes:  parseInt(tileNode.dataset.durationMinutes || '0', 10),
    publishedat:      tileNode.dataset.publishedAt ? parseInt(tileNode.dataset.publishedAt, 10) : null,
    isfeatured:       tileNode.dataset.isfeatured === '1',
    isnew:            tileNode.dataset.isnew === '1',
    tags:             tileNode.dataset.tags ? tileNode.dataset.tags.split('|').filter(Boolean) : [],
    // MLFR-269: derive the added state + course link from the rendered tile so the
    // detail modal shows "View Course" instead of "Add to Moodle" for added items.
    isadded:          tileNode.classList.contains('titus-tile--added'),
    courseurl:        tileNode.querySelector('.titus-view-btn')?.getAttribute('href') || '',
    isloading:        tileNode.classList.contains('titus-tile--loading'),
    isqueued:         tileNode.classList.contains('titus-tile--queued'),
    haserror:         tileNode.classList.contains('titus-tile--error'),
    errormessage:     '',
});

/**
 * Clear all child nodes without using innerHTML.
 *
 * @param {Element} node
 */
const clearChildren = (node) => {
    while (node.firstChild) {
        node.removeChild(node.firstChild);
    }
};

/**
 * Initialise the Titus marketplace on the given root element.
 *
 * @param {string} rootSelector CSS selector for the marketplace root.
 */
export const init = async(rootSelector) => {
    const root = document.querySelector(rootSelector || Selectors.regions.root);
    if (!root) {
        return;
    }

    // Prefetch resources to avoid waterfall on first interaction.
    prefetchTemplates([
        'local_tituscontentlibrary/tile',
        'local_tituscontentlibrary/empty_state',
        'local_tituscontentlibrary/category_pills',
        'local_tituscontentlibrary/detail_modal',
    ]);
    prefetchStrings('local_tituscontentlibrary', [
        'adding', 'added', 'addtomoodle', 'queued', 'processing', 'viewcourse',
        'toast:added', 'error:network', 'error:server', 'error:timeout',
        'announce:queueing', 'announce:queued', 'announce:processing',
        'announce:completed', 'announce:failed', 'announce:results',
        'emptysearch', 'emptycategory', 'clearsearch', 'showall', 'retry',
        'allcategories', 'filterbycategory',
        'sortby', 'sort:az', 'sort:za', 'sort:newest', 'sort:duration',
        'sort:category', 'sort:featured', 'sort:new',
        'badge:new', 'badge:featured',
        'detail:viewdetails', 'detail:close',
    ]);

    // Controller state.
    const state = {
        items:          [],
        activeCategory: '',
        searchQuery:    '',
        sort:           'az',
        stale:          false,
        requestSeq:     0,
    };

    // -------------------------------------------------------------------------
    // Announcement helper — writes to the aria-live region.
    // -------------------------------------------------------------------------
    const announce = async(stringkey, param) => {
        const announcer = root.querySelector(Selectors.regions.announcer);
        if (!announcer) {
            return;
        }
        const msg = await getString(stringkey, 'local_tituscontentlibrary', param);
        announcer.textContent = '';
        // Force re-read by screen readers via a brief timeout.
        setTimeout(() => {
            announcer.textContent = msg;
        }, 50);
    };

    // -------------------------------------------------------------------------
    // Rendering helpers.
    // -------------------------------------------------------------------------
    const renderTile = async(item) => {
        const {html, js} = await Templates.renderForPromise('local_tituscontentlibrary/tile', item);
        return {html, js};
    };

    const renderGrid = async(items) => {
        const gridNode = root.querySelector(Selectors.regions.grid);
        if (!gridNode) {
            return;
        }
        clearChildren(gridNode);
        for (const item of items) {
            const {html, js} = await renderTile(item);
            await Templates.appendNodeContents(gridNode, html, js);
        }
    };

    const renderEmptyState = async(variantFlags) => {
        const emptyNode = root.querySelector(Selectors.regions.emptyState);
        if (!emptyNode) {
            return;
        }
        const {html, js} = await Templates.renderForPromise(
            'local_tituscontentlibrary/empty_state',
            variantFlags
        );
        clearChildren(emptyNode);
        await Templates.appendNodeContents(emptyNode, html, js);
    };

    const applyFilters = async() => {
        state.requestSeq++;
        const mySeq = state.requestSeq;

        const afterCategory = categoryFilter(state.items, state.activeCategory);
        const afterSearch   = searchFilter(afterCategory, state.searchQuery);
        const afterSort     = sortItems(afterSearch, state.sort);

        const gridNode      = root.querySelector(Selectors.regions.grid);
        const searchClear   = root.querySelector(Selectors.actions.clearSearch);

        if (searchClear) {
            searchClear.hidden = !state.searchQuery;
        }

        if (state.requestSeq !== mySeq) {
            return;
        }

        if (afterSort.length === 0) {
            if (gridNode) {
                gridNode.setAttribute('hidden', '');
            }
            const flags = {
                nocatalogue:     false,
                stalecache:      false,
                nosearchresults: !!state.searchQuery,
                nocategorymatch: !state.searchQuery && !!state.activeCategory,
            };
            await renderEmptyState(flags);
            announce('announce:results', 0);
        } else {
            if (gridNode) {
                gridNode.removeAttribute('hidden');
            }
            await renderGrid(afterSort);
            await renderEmptyState({nocatalogue: false, stalecache: false, nosearchresults: false, nocategorymatch: false});
            announce('announce:results', afterSort.length);
        }
    };

    // -------------------------------------------------------------------------
    // Hydrate initial state from server-rendered tiles.
    // -------------------------------------------------------------------------
    const existingTiles = root.querySelectorAll(Selectors.regions.tile);
    if (existingTiles.length > 0) {
        existingTiles.forEach(t => state.items.push(hydrateFromDom(t)));
    } else {
        // Page was rendered with no tiles — try fetching catalogue from server.
        try {
            const result = await Repository.getCatalogue();
            state.items = result.items || [];
            state.stale = result.stale;
            if (state.items.length > 0) {
                await renderGrid(state.items);
            }
        } catch(err) {
            Notification.exception(err);
        }
    }

    // -------------------------------------------------------------------------
    // Update category pills to reflect current categories.
    // -------------------------------------------------------------------------
    const updateCategoryPills = async() => {
        const pillsNode = root.querySelector(Selectors.regions.categories);
        if (!pillsNode) {
            return;
        }
        const categories = [...new Set(state.items.map(i => i.category).filter(Boolean))];
        categories.sort((a, b) => a.localeCompare(b));
        const pillCtx = {
            allactive:  !state.activeCategory,
            categories: categories.map(cat => ({
                id:       cat.toLowerCase().replace(/[^a-z0-9]/g, ''),
                name:     cat,
                isactive: cat.toLowerCase().replace(/[^a-z0-9]/g, '') === state.activeCategory,
            })),
        };
        const {html, js} = await Templates.renderForPromise('local_tituscontentlibrary/category_pills', pillCtx);
        clearChildren(pillsNode);
        await Templates.appendNodeContents(pillsNode, html, js);
    };

    await updateCategoryPills();

    // -------------------------------------------------------------------------
    // Initialise sort from the server-rendered preference (data attribute).
    // -------------------------------------------------------------------------
    const sortSelect = root.querySelector(Selectors.regions.sort);
    if (sortSelect) {
        state.sort = sortSelect.dataset.sortOption || 'az';
        sortSelect.value = state.sort;
    }

    // -------------------------------------------------------------------------
    // Refresh full catalogue from the API.
    // -------------------------------------------------------------------------
    const refreshCatalogue = async() => {
        try {
            const result = await Repository.getCatalogue();
            state.items = result.items || [];
            state.stale = result.stale;
            await updateCategoryPills();
            await applyFilters();
        } catch(err) {
            Notification.exception(err);
        }
    };

    // -------------------------------------------------------------------------
    // Category filter handler.
    // -------------------------------------------------------------------------
    const handleFilterCategory = async(categoryId) => {
        state.activeCategory = categoryId;
        // Update aria-pressed on pills.
        root.querySelectorAll(Selectors.actions.filterCategory).forEach(btn => {
            const active = btn.dataset.categoryId === categoryId;
            btn.setAttribute('aria-pressed', String(active));
            btn.classList.toggle('active', active);
        });
        await applyFilters();
    };

    // -------------------------------------------------------------------------
    // Search clear handler.
    // -------------------------------------------------------------------------
    const handleClearSearch = async() => {
        const searchInput = root.querySelector(Selectors.regions.searchInput);
        if (searchInput) {
            searchInput.value = '';
        }
        state.searchQuery = '';
        await applyFilters();
    };

    // -------------------------------------------------------------------------
    // Debounced search input handler.
    // -------------------------------------------------------------------------
    let searchTimer = null;
    root.addEventListener('input', (e) => {
        const input = e.target.closest(Selectors.regions.searchInput);
        if (!input) {
            return;
        }
        clearTimeout(searchTimer);
        searchTimer = setTimeout(async() => {
            state.searchQuery = input.value;
            await applyFilters();
        }, DEBOUNCE_MS);
    });

    // -------------------------------------------------------------------------
    // Sort change handler — persists preference via core_user WS.
    // -------------------------------------------------------------------------
    root.addEventListener('change', async(e) => {
        const select = e.target.closest(Selectors.regions.sort);
        if (!select) {
            return;
        }
        state.sort = select.value;
        // Persist user preference — fire-and-forget, failure is non-critical.
        Ajax.call([{
            methodname: 'core_user_set_user_preferences',
            args: {preferences: [{name: 'local_tituscontentlibrary_sort', value: state.sort}]},
        }]);
        await applyFilters();
    });

    // -------------------------------------------------------------------------
    // Delegated click handler — single listener on root.
    // -------------------------------------------------------------------------
    root.addEventListener('click', async(e) => {
        const addBtn    = e.target.closest(Selectors.actions.add);
        const pillBtn   = e.target.closest(Selectors.actions.filterCategory);
        const clrBtn    = e.target.closest(Selectors.actions.clearSearch);
        const retryBtn  = e.target.closest(Selectors.actions.retryCatalogue);

        if (addBtn) {
            const tile = addBtn.closest(Selectors.regions.tile);
            if (tile) {
                await TileController.handleAdd(tile, announce, Repository);
            }
            return;
        }

        const detailBtn = e.target.closest('[data-action="open-detail"]');
        if (detailBtn) {
            const tile = detailBtn.closest(Selectors.regions.tile);
            if (tile) {
                const contentid = tile.dataset.contentId;
                const item = state.items.find(i => i.id === contentid);
                if (item) {
                    await DetailModal.show(item, detailBtn, announce, Repository);
                }
            }
            return;
        }

        if (pillBtn) {
            await handleFilterCategory(pillBtn.dataset.categoryId || '');
            return;
        }
        if (clrBtn) {
            await handleClearSearch();
            return;
        }
        if (retryBtn) {
            await refreshCatalogue();
        }
    });
};
