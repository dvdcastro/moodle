<?php
namespace local_tituscontentlibrary\output;

defined('MOODLE_INTERNAL') || die();

class renderer extends \plugin_renderer_base {

    public function render_marketplace_page(marketplace_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_tituscontentlibrary/marketplace', $data);
    }
}
