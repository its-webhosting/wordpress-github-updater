# U-M Wordpress Github Updater
[![GitHub release](https://img.shields.io/github/release/umdigital/wordpress-github-updater.svg)](https://github.com/umdigital/wordpress-github-updater/releases/latest)
[![GitHub issues](https://img.shields.io/github/issues/umdigital/wordpress-github-updater.svg)](https://github.com/umdigital/wordpress-github-updater/issues)

Provides the ability to update a wordpress plugin using Github instead of wordpress.

## Installation & Usage
#### Add composer package to your plugin
```bash
composer require umdigital/wordpress-github-updater
```

#### Update Plugin Header
Add the following to your [plugins header](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/)
```
Update URI: https://github.com/GITHUB_ACCOUNT/GITHUB_REPO
```

#### Initialize the library
```php
// Omit this include if you're using an autoloader
include 'vendor/umdigital/wordpress-github-updater/github-updater.php';

// Initialize Github Updater
new \Umich\GithubUpdater\Init([
    'repo' => 'its-cloudflare/umich-cloudflare',
    'slug' => plugin_basename( __FILE__ ),
]);
```

#### Create / Update your build process
For best support it is recommended to add a release workflow that will automatically package the plugin into a wordpress compatible zip file.  The default github source archives cause irregular plugin folder naming during install and updates.  There are some options of workflows in the examples directory. These workflows will create a release when a tag is pushed to the repo.  The one with composer in the name will also add composer dependencies to the package. This will create a release asset in the format of [REPO_NAME]-[TAG_NAME].zip.

## Configuration
### Initialization Options
| Option         | Required | Default         | Description                           |
| -------------- | -------- | --------------- | ------------------------------------- |
| repo           | Yes      |                 | Path to your github repo e.g. umdigital/wordpress-github-updater |
| slug           | Yes      |                 | Plugin slug e.g. my-plugin/my-plugin.php, can use `plugin_basename( __FILE__ )`|
| match_releases | No       |                 | Specifies which releases to upgrade to, see below for details |
| config         | No       | wordpress.json  | See below for options                 |
| changelog      | No       | CHANGELOG       | Provide Changelog information in the plugin info admin panel |
| description    | No       | README.md       | Provide plugin information in the plugin info admin panel |
| cache_timeout  | No       | 21600 (6 hours) | How long to cache github plugin data |

### Config File (wordpress.json) Options
| Option       | Required | Description                           |
| ------------ | -------- | ------------------------------------- |
| requires     | No       | Minimum Wordpress Version             |
| tested       | No       | Maximum Wordpress Version Tested      |
| requires_php | No       | Minimum PHP Version required          |
| banners:low  | No       | Plugin info banner image (750 x 250)  |
| banners:high | No       | Plugin info banner image (1500 x 500) |

### `match_releases` Values

`match_releases` defaults to an empty string, which causes the plugin to consider only the latest published, stable release (the one at `${REPO_URL}/releases/latest`) for upgrading.

`match_releases` can be a comma separated list of case-sensitive keywords in the table below, with the latter keywords overriding earlier ones:

| Name           | Description                                                 |
|----------------|-------------------------------------------------------------|
| `stable`       | Upgrade only to published, stable releases                  |
| `includeRC`    | Published release candidates (`-rc1`, ...) and stable releases |
| `includeBeta`  | Published beta releases, RCs, and stable releases           |
| `includeAlpha` | Published alphas, betas, RCs, and full releases             |
| `includeAll`   | Upgrade to the published release with the largest version number, regardless of type |
| `pinMajor`     | Stay on the major version of the currently installed plugin |

Otherwise, `match_releases` will be used as a regular expression and the published release with the _highest [SemVer version number](https://semver.org/)_ (not latest date!) that matches the regex will be compared against the current version of the plugin to determine if an upgrade is available for the plugin.


For example, to upgrade the plugin to beta, rc, and stable releases that have higher version numbers than what is currently installed:
```php
new \Umich\GithubUpdater\Init([
    'repo'           => 'its-cloudflare/umich-cloudflare',
    'slug'           => plugin_basename( __FILE__ ),
    'match_releases' => 'includeBeta',
]);
```

Upgrade to any stable, RC, or beta release in the 3.x series, but don't upgrade to higher major version numbers:
```php
new \Umich\GithubUpdater\Init([
    'repo'           => 'its-cloudflare/umich-cloudflare',
    'slug'           => plugin_basename( __FILE__ ),
    'match_releases' => '/^v3\.[0-9.]+(-(beta|rc))?/i',
```
If the plugin major version is already 3.x, an easier way to do the same thing is to use the value `includeBeta,pinMajor`

The plugin may choose to have a setting to allow administrators to select which types of upgrades they want to opt into.  For an example UI, see the [WordPress GitHub Updater Demo](https://github.com/its-webhosting/wordpress-github-updater-demo) plugin.

WordPress administrators can also override the value `match_releases` provided by the plugin author by running
```bash
wp option set github-updater-override-${plugin_slug} '${value}'
```
For example, to lock the `umich-cloudflare` plugin to only stable 1.x releases:
```bash
wp option set github-updater-override-umich-cloudflare 'stable,pinMajor'
```
or, if you prefer to use a regular expression:
```bash
wp option set github-updater-override-umich-cloudflare '/^v1\.[0-9.]+[^-]*(+.*)?$/i'
```
