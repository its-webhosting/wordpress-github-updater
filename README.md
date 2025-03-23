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

`match_releases` defaults to an empty string, which causes the plugin to consider only the latest regular/published release (the one at `${REPO_URL}/releases/latest`) for upgrading.

If `match_releases` is a non-empty string, it will be used as a regular expression and the release with the _highest [SemVer version number](https://semver.org/)_ (not latest date!) that matches the regex will be compared against the current version of the plugin to determine if an upgrade is available for the plugin.

PHP namespace `\Umich\GithubUpdater` has several variables that can be used for setting `match_releases`:

| Name                           | Description                                            |
| ------------------------------ | ------------------------------------------------------ |
| `MatchReleases::$latest`       | Upgrade only to full (normal) releases                 |
| `MatchReleases::$includeRC`    | Release candidates (`-rc1`, ...) and full releases     |
| `MatchReleases::$includeBeta`  | Beta releases, RCs, and full releases                  |
| `MatchReleases::$includeAlpha` | Alphas, betas, RCs, and full releases                  |
| `MatchReleases::$includeAll`   | Upgrade to the release with the largest version number |

For example, to upgrade the plugin to beta and regular releases that have higher version numbers than what is currently installed:
```php
new \Umich\GithubUpdater\Init([
    'repo'           => 'its-cloudflare/umich-cloudflare',
    'slug'           => plugin_basename( __FILE__ ),
    'match_releases' => \Umich\GithubUpdater\MatchReleases::$includeBeta,
]);
```

Upgrade to any normal, RC, or beta release in the 3.x series, but don't upgrade to higher major version numbers:
```php
new \Umich\GithubUpdater\Init([
    'repo'           => 'its-cloudflare/umich-cloudflare',
    'slug'           => plugin_basename( __FILE__ ),
    'match_releases' => '/^v3\.[0-9.]+(-(beta|rc))?/i',
```

The plugin may choose to have a setting to allow administrators to select which types of upgrades they want to opt into.  For an example UI, see the [WordPress GitHub Updater Demo](https://github.com/its-webhosting/wordpress-github-updater-demo) plugin.

WordPress administrators can also override the value `match_releases` provided by the plugin author through `\Umich\GithubUpdater\Init()` by running
```bash
wp option set github-updater-override-${plugin_slug} '${upgrade_regex}'
```
For example, to lock the `umich-cloudflare` plugin to only full/regular 1.x releases:
```bash
wp option set github-updater-override-umich-cloudflare '/^v1\.[0-9.]+[^-]*(+.*)?$/i'
```
