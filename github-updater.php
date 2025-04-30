<?php

/**
 * Name: U-M: Wordpress Github Updater Library
 * Description: Provides simple method to distribute releases using github rather than wordpress plugin repo.
 * Version: 1.1.0
 * Project URI: https://github.com/umdigital/wordpress-github-updater
 * Author: U-M: OVPC Digital
 * Author URI: https://vpcomm.umich.edu
 */

namespace Umich\GithubUpdater {

    use Composer\Semver\Comparator;

    if( !class_exists( '\Umich\GithubUpdater\Init' ) ) {
        class Init
        {
            static private $_version = '0';

            public function __construct( $options = array() )
            {
                $class = '\Umich\GithubUpdater\v'. strtr( self::$_version, '.-+', 'd_P' ) .'\Actions';
                new $class( $options );
            }

            static public function load( $version )
            {
                if( Comparator::greaterThanOrEqualTo( $version, self::$_version ) ) {
                    self::$_version = $version;
                }
            }
        }
    }
}


namespace Umich\GithubUpdater\v1d1d0 {

    use Composer\Semver\Comparator;

    if( !class_exists( '\Umich\GithubUpdater\v1d1d0\Actions' ) ) {
        class Actions
        {
            CONST VERSION = '1.1.0';

            private $_githubBase = [
                'main' => 'https://github.com/',
                'api'  => 'https://api.github.com/repos/',
                'raw'  => 'https://raw.githubusercontent.com/',
            ];

            private $_requiredOptions = [
                'repo',
                'slug',
            ];

            private $_options = [
                'repo'           => '',
                'slug'           => '',
                'match_releases' => '',
                'config'         => 'wordpress.json',
                'changelog'      => 'CHANGELOG',
                'description'    => 'README.md',
                'cache_timeout'  => 60 * 60 * 6, // 6 hours
            ];

            private $_data = [];

            public function __construct( $options )
            {
                // dynamic defaults
                $this->_options['slug'] = plugin_basename( __FILE__ );

                // remove keys not used
                $options = array_intersect_key( $options, $this->_options );

                // override defaults
                $this->_options = array_merge(
                    $this->_options, $options
                );

                // check for required options
                $invalidOptions = [];
                foreach( $this->_requiredOptions as $key ) {
                    if( empty( $this->_options[ $key ] ) ) {
                        $invalidOptions[] = $key;
                    }
                }

                if( $invalidOptions && function_exists( '\_doing_it_wrong' ) ) {
                    \_doing_it_wrong(
                        '\Umich\GithubUpdater\Init',
                        'Missing required options: '. implode( ', ', $invalidOptions ) .'.',
                        self::VERSION
                    );
                }

                // maybe override match_releases (if set by administrator)
                $override = get_option( 'github-updater-override-' . $this->_options['slug'], 'not_set' );
                if ( is_string( $override ) && $override !== 'not_set' ) {
                    $this->_options['match_releases'] = $override;
                }

                /** WORDPRESS HOOKS **/
                // Update Check
                add_filter( 'update_plugins_github.com', function( $update, $pluginData, $pluginFile ){
                    if( $pluginFile == $this->_options['slug'] ) {
                        // get latest release
                        if ( empty( $this->_options['match_releases'] )
                             || $this->_options['match_releases'] != 'stable' ) {
                            $release = $this->_callAPI( 'releases/latest', 'gh_release_latest' );
                        } else {
                            $pluginData = get_plugin_data( WP_PLUGIN_DIR .'/'. $this->_options['slug'] );
                            $release = $this->_searchAllReleases( $pluginData );
                        }

                        if( $release ) {
                            $update = [
                                'slug'    => $this->_options['slug'],
                                'version' => $release->tag_name,
                                'url'     => $this->_githubBase['main'] . $this->_options['repo'] .'/releases/tag/'
                                             . $release->tag_name,
                                'package' => $release->zipball_url
                            ];

                            foreach( $release->assets as $asset ) {
                                if( $asset->name == basename( $this->_options['repo'] ) ."-{$release->tag_name}.zip" ) {
                                    $update['package'] = $asset->browser_download_url;
                                }
                            }
                        }
                    }

                    return $update;
                }, 10, 3 );

                // Plugin Details
                add_filter( 'plugins_api', function( $return, $action, $args ){
                    if( !isset( $args->slug ) || ($args->slug != $this->_options['slug']) ) {
                        return $return;
                    }

	                $pluginData = get_plugin_data( WP_PLUGIN_DIR .'/'. $this->_options['slug'] );

	                if ( empty( $this->_options['match_releases'] )
                         || $this->_options['match_releases'] != 'stable' ) {
                        $release = $this->_callAPI( 'releases/latest', 'gh_release_latest' );
                    } else {
                        $release = $this->_searchAllReleases( $pluginData );
                    }

                    if( $release && $pluginData ) {
                        if( ($wpConfig = $this->_getRaw( $this->_options['config'], $release->tag_name )) !== false ) {
                            foreach( [ 'description', 'changelog' ] as $key ) {
                                if( isset( $wpConfig->{$key} ) ) {
                                    $this->_options[ $key ] = $wpConfig->{$key};
                                }
                            }
                        }

                        $return = (object) [
                            'slug'           => $args->slug,
                            'name'           => $pluginData['Name'],
                            'version'        => $release->tag_name,
                            'requires'       => '',
                            'tested'         => '',
                            'requires_php'   => '',
                            'last_updated'   => date( 'Y-m-d h:ia e', strtotime( $release->published_at ) ),
                            'author'         => $pluginData['Author'],
                            'homepage'       => $pluginData['PluginURI'],
                            'sections'       => [ // as html
                                'description' => $this->_getMarkdown(
                                    $this->_options['description'],
                                    $release->tag_name,
                                    $pluginData['Description'] ?: $pluginData['Name']
                                ),
                                'changelog'   => $this->_getMarkdown(
                                    $this->_options['changelog'],
                                    $release->tag_name,
                                    $release->body
                                ),
                            ],
                            'download_link'  => $release->zipball_url, // zip file
                            'banners'        => [
                                'low'  => '', // image link (750x250)
                                'high' => '', // image link large (1500x500)
                            ]
                        ];

                        foreach( $release->assets as $asset ) {
                            if( $asset->name == basename( $this->_options['repo'] ) ."-{$release->tag_name}.zip" ) {
                                $return->download_link = $asset->browser_download_url;
                            }
                        }

                        if( $wpConfig ) {
                            foreach( array( 'requires', 'tested', 'requires_php', 'banners:low', 'banners:high' ) as $key ) {
                                if( isset( $wpConfig->{$key} ) ) {
                                    if( strpos( $key, ':' ) !== false ) {
                                        $kParts = explode( ':', $key, 2 );
                                        $return->{$kParts[0]}[ $kParts[1] ] = $wpConfig->{$key};
                                    }
                                    else {
                                        $return->{$key} = $wpConfig->{$key};
                                    }
                                }
                            }
                        }

                        foreach( $return->banners as $key => $img ) {
                            if( strpos( $img, '/' ) === 0 ) {
                                $return->banners[ $key ] = plugins_url( $img, dirname( __FILE__ ) );
                            }
                        }
                    }

                    return $return;
                }, 20, 3 );

                // force directory name to stay the same
                add_filter( 'upgrader_post_install', function( $true, $extra, $result ){
                    global $wp_filesystem;

                    $newDest = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->_options['slug'] );

                    $wp_filesystem->move( $result['destination'], $newDest );

                    $result['destination'] = $newDest;

                    activate_plugin( WP_PLUGIN_DIR . $this->_options['slug'] );

                    return $result;
                }, 10, 3 );
            }

            private function _callAPI( $endpoint, $key = null, $method = 'GET', $data = null )
            {
                if( $key && isset( $this->_data[ $key ] ) ) {
                    return $this->_data[ $key ];
                }

                $params = [
                    'timeout' => 5,
                    'method'  => $method,
                    'headers' => []
                ];

                if( $data ) {
                    $params['body']    = is_string( $data ) ? $data : json_encode( $data );
                    $params['headers'] = array_merge( $params['headers'], [
                        'Accept'       => 'application/vnd.github+json',
                        'Content-Type' => 'application/json',
                    ]);
                }

                $data = false;

                if( $key && ! isset( $_GET['force-check'] ) ) {
                    $data = get_site_transient( $this->_getTransientKey( $key ) );
                }

                if( !$data ) {
                    $url = rtrim( "{$this->_githubBase['api']}{$this->_options['repo']}/{$endpoint}", '/' );
                    //$url .= '?per_page=2';  // uncomment for testing
	                $data = [];
                    $pagesRemaining = true;
                    while ( $pagesRemaining ) {
                        $res = wp_remote_request( $url, $params );

                        if( is_wp_error( $res ) ) {
                            error_log( "wordpress-github-updater: {$this->_options['slug']}: Error calling API: "
                                       . $res->get_error_message() );
                            return false;
                        }
                        if ( $res['response']['code'] != 200 ) {
                            error_log( "wordpress-github-updater: {$this->_options['slug']}: Error calling API: response code "
                                       . $res['response']['code'] );
                            return false;
                        }

                        $pagesRemaining = isset( $res['headers']['link'] );
                        if ( $pagesRemaining ) {
                            if ( preg_match( '/<([^>]+)>; rel="next"/i', $res['headers']['link'], $matches ) ) {
                                $url = $matches[1];
                            } else {
                                $pagesRemaining = false;
                            }
                        }

                        $d = json_decode( $res['body'] );
                        if ( ! $d ) { $d = []; }
                        if ( ! is_array( $d ) ) { $d = [ $d ]; }
                        $data = array_merge( $data, $d );
                    }

                    if( $key ) {
                        set_site_transient(
                            $this->_getTransientKey( $key ),
                            $data,
                            $this->_options['cache_timeout']
                        );

                        $this->_data[ $key ] = $data;
                    }
                }
                if ( count( $data ) === 1 ) { return $data[0]; }
                return $data;
            }

            private function _searchAllReleases( $pluginData )
            {
                $matchReleases = ! empty( $this->_options['match_releases'] )
                    ? $this->_options['match_releases'] : 'stable';

                // handle match_releases keywords
                $pinMajor = false;
                $matchType = 'regex';
                $keywords = explode( ',', $matchReleases );
                foreach ( $keywords as $kw ) {
                    $kw = trim( $kw );
                    switch ( $kw ) {
                        case 'pinMajor':
                            $pinMajor = true;
                            if ( $matchType == 'regex' ) {
                                $matchType = 'stable';
                            }
                            break;
                        case 'stable':
                            $matchType = 'stable';
                            break;
                        case 'includeRC':
                            $matchType = 'includeRC';
                            break;
                        case 'includeBeta':
                            $matchType = 'includeBeta';
                            break;
                        case 'includeAlpha':
                            $matchType = 'includeAlpha';
                            break;
                        case 'includeAll':
                            $matchType = 'includeAll';
                            break;
                        default:
                            break;
                    }
                }

                if ( $matchType != 'regex' ) {
                    $re_prefix = '/^v?';
                    if ( $pinMajor ) {
                        if ( ! $pluginData || ! isset( $pluginData[ 'Version' ] ) ) {
                            error_log( "wordpress-github-updater: {$this->_options['slug']}: No plugin version found to use with pinMajor" );
                            return null;
                        }
                        if ( ! preg_match( '/^\s*v\s*([0-9]+)\./i', $pluginData[ 'Version' ], $majorVersion ) ) {
                            error_log( "wordpress-github-updater: {$this->_options['slug']}: Unable to determine major version for pinMajor" );
                            return null;
                        }
                        $re_prefix = '/^v?' . $majorVersion[1] . '\.';
                    }

                    $matchRegexes = [
                        'stable'       => '[0-9.]+\+?/i',
                        'includeRC'    => '[0-9.]+(-rc)?/i',
                        'includeBeta'  => '[0-9.]+(-(beta|rc))?/i',
                        'includeAlpha' => '[0-9.]+(-(alpha|beta|rc))?/i',
                        'includeAll'   => '[0-9.]+/i',
                    ];
                    if ( ! isset( $matchRegexes[ $matchType ] ) ) {
                        error_log( "wordpress-github-updater: {$this->_options['slug']}: Invalid match_releases value: {$matchType}" );
                        return null;
                    }
                    $matchReleases = $re_prefix . $matchRegexes[ $matchType ];
                }

                $release = null;
                $releases = $this->_callAPI( 'releases', 'gh_all_releases' );
                foreach( $releases as $r ) {
                    if( preg_match( $matchReleases, $r->tag_name ) ) {
                        if ( ! $release || Comparator::greaterThan( $r->tag_name, $release->tag_name ) ) {
                            $release = $r;
                        }
                    }
                }
                return $release;
            }

            private function _getRaw( $file, $version = null )
            {
                $asset = trim( "{$version}/{$file}", '/' );

                $url = "{$this->_githubBase['raw']}{$this->_options['repo']}/{$asset}";

                $res = wp_remote_get( $url );

                if( is_wp_error( $res ) || (@$res['response']['code'] != 200) ) {
                    return false;
                }

                return $res['body'];
            }

            private function _getTransientKey( $key )
            {
                return substr( $this->_options['repo'], 0, 100 ) .'-'. $key;
            }

            private function _getMarkdown( $file, $version = null, $default = '' )
            {
                if( ($content = $this->_getRaw( $file, $version )) !== false ) {
                    $mRes = wp_remote_post(
                        'https://api.github.com/markdown', [
                            'body'    => json_encode([ 'text' => $content ]),
                            'timeout' => 5,
                            'headers' => [
                                'Accept'       => 'application/vnd.github+json',
                                'Content-Type' => 'application/json',
                            ]
                        ]
                    );

                    if( !is_wp_error( $mRes ) && @$mRes['response']['code'] == 200 ) {
                        return $mRes['body'];
                    }
                }

                return $default;
            }
        }

        \Umich\GithubUpdater\Init::load( Actions::VERSION );
    }
}