<?php
namespace local_tituscontentlibrary\output;

defined('MOODLE_INTERNAL') || die();

class marketplace_page implements \renderable, \templatable {

    /** @var array Array of content_dto objects. */
    private array $contents;

    /** @var string[] Sorted category name list. */
    private array $categories;

    /** @var bool Whether the catalogue data is stale. */
    private bool $stale;

    /** @var bool Whether the catalogue is completely unavailable (no data at all). */
    private bool $errorstate;

    public function __construct(array $contents, array $categories, bool $stale, bool $errorstate = false) {
        $this->contents   = $contents;
        $this->categories = $categories;
        $this->stale      = $stale;
        $this->errorstate = $errorstate;
    }

    public function export_for_template(\renderer_base $output): array {
        global $DB;

        $sort = get_user_preferences('local_tituscontentlibrary_sort', 'az');

        // Look up the per-content state from the local added-content table so the
        // marketplace tiles reflect the current state on initial server-side render
        // (MLFR-262). Keyed by contentid → row (status + courseid for the View Course
        // link). Without this, already-added tiles render as "Add" until the AJAX
        // refresh re-fetches state.
        $rowbyid   = [];
        $addedrows = $DB->get_records(
            'local_tituscontentlibrary_added',
            null,
            '',
            'id, contentid, courseid, status'
        );
        foreach ($addedrows as $row) {
            $rowbyid[$row->contentid] = $row;
        }

        $contents = [];
        foreach ($this->contents as $dto) {
            $addedrow = $rowbyid[$dto->id] ?? null;
            $status   = $addedrow->status ?? '';
            $iscompleted = $status === \local_tituscontentlibrary\local\content_status::COMPLETED;

            // Build the View Course link for completed imports that still have a course
            // (MLFR-263). Empty otherwise so the tile renders the Add control.
            $courseurl = '';
            if ($iscompleted && !empty($addedrow->courseid)) {
                $courseurl = (new \moodle_url('/course/view.php', ['id' => $addedrow->courseid]))->out(false);
            }

            // Reuse the shared mapper so the server render emits the exact same field
            // shape as the AJAX path (description, version, updatedat, subcategory, etc.)
            // — MLFR-263 omitted these on first render.
            $tile = \local_tituscontentlibrary\local\catalogue_mapper::dto_to_array($dto);
            $tile['thumbnailurl'] = clean_param($dto->thumbnail_url, PARAM_URL);
            $tile['status']       = $status;
            $tile['isadded']      = $iscompleted;
            $tile['courseurl']    = $courseurl;
            $tile['isloading']    = $status === \local_tituscontentlibrary\local\content_status::PROCESSING;
            $tile['isqueued']     = $status === \local_tituscontentlibrary\local\content_status::PENDING;
            $tile['haserror']     = $status === \local_tituscontentlibrary\local\content_status::FAILED;
            $tile['errormessage'] = '';
            $contents[] = $tile;
        }

        $categories = [];
        foreach ($this->categories as $cat) {
            $categories[] = [
                'id'       => preg_replace('/[^a-z0-9]/', '', \core_text::strtolower($cat)),
                'name'     => $cat,
                'isactive' => false,
            ];
        }

        $emptystate        = empty($contents);
        $emptystatecontext = [
            'nocatalogue'    => $this->errorstate,
            'stalecache'     => !$this->errorstate && $this->stale && empty($contents),
            'nosearchresults'=> false,
            'nocategorymatch'=> false,
        ];

        return [
            'contents'          => $contents,
            'categories'        => $categories,
            'allactive'         => true,
            'emptystate'        => $emptystate,
            'emptystatecontext' => $emptystatecontext,
            'errorstate'        => $this->errorstate,
            'stale'             => $this->stale && !empty($contents),
            'sortoption'        => $sort,
            'tilesperpage'      => \local_tituscontentlibrary\config::get_tiles_per_page(),
        ];
    }
}
