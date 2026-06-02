<?php
namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

/**
 * API health monitoring for the Titus catalogue refresh pipeline.
 *
 * Tracks success/failure streaks in plugin config (no schema changes required).
 * Notifies site admins via the messaging API when the failure streak exceeds the
 * configured threshold.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class monitoring {

    const CFG_LAST_SUCCESS    = 'monitor_last_success';
    const CFG_FAILURE_STREAK  = 'monitor_failure_streak';
    const CFG_TOTAL_REFRESHES = 'monitor_total_refreshes';

    /**
     * Record a successful catalogue refresh.
     */
    public static function record_success(): void {
        set_config(self::CFG_LAST_SUCCESS, time(), 'local_tituscontentlibrary');
        set_config(self::CFG_FAILURE_STREAK, 0, 'local_tituscontentlibrary');
        $total = (int) get_config('local_tituscontentlibrary', self::CFG_TOTAL_REFRESHES);
        set_config(self::CFG_TOTAL_REFRESHES, $total + 1, 'local_tituscontentlibrary');
    }

    /**
     * Record a failed catalogue refresh.
     *
     * Notifies admins when the streak reaches the configured threshold.
     */
    public static function record_failure(): void {
        $streak = (int) get_config('local_tituscontentlibrary', self::CFG_FAILURE_STREAK);
        $streak++;
        set_config(self::CFG_FAILURE_STREAK, $streak, 'local_tituscontentlibrary');

        $threshold = (int) get_config('local_tituscontentlibrary', 'failurestreakthreshold') ?: 3;
        if ($streak >= $threshold) {
            self::notify_admins($streak);
        }
    }

    /**
     * Return current monitoring stats.
     *
     * @return array{last_success: int, failure_streak: int, total_refreshes: int}
     */
    public static function get_stats(): array {
        return [
            'last_success'    => (int) get_config('local_tituscontentlibrary', self::CFG_LAST_SUCCESS),
            'failure_streak'  => (int) get_config('local_tituscontentlibrary', self::CFG_FAILURE_STREAK),
            'total_refreshes' => (int) get_config('local_tituscontentlibrary', self::CFG_TOTAL_REFRESHES),
        ];
    }

    /**
     * Return an HTML badge indicating the current API health.
     *
     * Never calls the Titus API — reads only from plugin config.
     *
     * @return string HTML badge string.
     */
    public static function render_indicator(): string {
        $streak    = (int) get_config('local_tituscontentlibrary', self::CFG_FAILURE_STREAK);
        $threshold = (int) get_config('local_tituscontentlibrary', 'failurestreakthreshold') ?: 3;

        if ($streak === 0) {
            $cls  = 'badge text-bg-success';
            $text = get_string('monitor:ok', 'local_tituscontentlibrary');
        } elseif ($streak < $threshold) {
            $cls  = 'badge text-bg-warning';
            $text = get_string('monitor:warning', 'local_tituscontentlibrary', $streak);
        } else {
            $cls  = 'badge text-bg-danger';
            $text = get_string('monitor:critical', 'local_tituscontentlibrary', $streak);
        }

        return \html_writer::span($text, $cls);
    }

    /**
     * Send a message to all site admins about the failure streak.
     *
     * @param int $streak Current consecutive failure count.
     */
    public static function notify_admins(int $streak): void {
        global $CFG;
        require_once($CFG->dirroot . '/message/lib.php');

        $noreply = \core_user::get_noreply_user();
        foreach (get_admins() as $admin) {
            $msg = new \core\message\message();
            $msg->component         = 'local_tituscontentlibrary';
            $msg->name              = 'refreshfailure';
            $msg->userfrom          = $noreply;
            $msg->userto            = $admin;
            $msg->subject           = get_string('monitor:notify:subject', 'local_tituscontentlibrary');
            $msg->fullmessage       = get_string('monitor:notify:body', 'local_tituscontentlibrary', $streak);
            $msg->fullmessageformat = FORMAT_PLAIN;
            $msg->fullmessagehtml   = '';
            $msg->smallmessage      = get_string('monitor:notify:subject', 'local_tituscontentlibrary');
            $msg->courseid          = SITEID;
            message_send($msg);
        }
    }
}
