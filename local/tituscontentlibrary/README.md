# local_tituscontentlibrary

Moodle local plugin providing a SCORM content marketplace powered by the Titus Learning Content Library API.

## Requirements

- Moodle 4.5+ (requires 2024100700)
- PHP 8.1+
- `mod_scorm` enabled
- A valid Titus Learning licence key

## Installation

1. Copy the `local/tituscontentlibrary` folder into your Moodle `local/` directory.
2. Visit **Site Administration > Notifications** and run the upgrade.
3. Navigate to **Site Administration > Plugins > Local plugins > Titus Content Library** and configure:
   - **Titus API base URL** — the API endpoint (e.g. `https://api.tituslearning.com`)
   - **Licence key** — your Titus licence key (stored encrypted)
   - **Default course category** — where imported courses land
   - **Catalogue cache lifetime** — how often the catalogue refreshes (default: 1 hour)

## Usage

Navigate to `/local/tituscontentlibrary/index.php`. Users with the `local/tituscontentlibrary:view` capability (managers, editing teachers) see the full catalogue. Click **Add to Moodle** on any tile to queue an async import — the course appears in the selected category once the background task completes.

### Capabilities

| Capability | Default roles |
|---|---|
| `local/tituscontentlibrary:view` | manager, editingteacher, teacher |
| `local/tituscontentlibrary:addcontent` | manager, editingteacher |
| `local/tituscontentlibrary:manageintegration` | manager |

## Troubleshooting

- **Catalogue not loading** — check the API URL and licence key in settings. Check `admin/cli/scheduled_task.php` to run the refresh task manually.
- **Add to Moodle stuck** — check the adhoc task queue (`admin/tool/task/adhoctasks.php`). The task log shows the error.
- **mod_scorm warning** — enable the SCORM activity module in **Site Administration > Plugins > Manage activities**.

## Development / Simulator

Use `local_titusclsim` as a local API simulator — see `local/titusclsim/README.md`. Set the API base URL to `http://localhost/local/titusclsim/api` and the licence key to `TITUS-FULL-KEY-001`.

## License

GPL v3 or later — http://www.gnu.org/copyleft/gpl.html
