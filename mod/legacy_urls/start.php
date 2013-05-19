<?php
/**
 * Provides support for URLs no longer used in Elgg for those who bookmarked or
 * linked to them
 */

elgg_register_event_handler('init', 'system', 'legacy_urls_init');

function legacy_urls_init() {
	elgg_register_page_handler('tag', 'legacy_urls_tag_handler');
	elgg_register_page_handler('pg', 'legacy_urls_pg_handler');
	elgg_register_plugin_hook_handler('route', 'blog', 'legacy_url_blog_forward');
}

/**
 * Redirect the requestor to the new URL
 * 
 * @param string $url Relative or absolute URL
 */
function legacy_urls_redirect($url) {
	$method = elgg_get_plugin_setting('redirect_method', 'legacy_urls');

	// we only show landing page or queue warning if html generating page
	$viewtype = elgg_get_viewtype();
	if ($viewtype != 'default' && !elgg_does_viewtype_fallback($viewtype)) {
		$method = 'immediate';
	}

	switch ($method) {
		case 'landing':
			$content = elgg_view('legacy_urls/message', array('url' => $url));
			$body = elgg_view_layout('error', array('content' => $content));
			echo elgg_view_page('', $body, 'error');
			return true;
			break;
		case 'immediate_error':
			// drop through after setting error message
			register_error(elgg_echo('changebookmark'));
		case 'immediate':
		default:
			$url = elgg_normalize_url($url);
			header("HTTP/1.1 301 Moved Permanently");
			header("Location: $url");
			exit;
			break;
	}
}

/**
 * Adds query parameters to URL for redirect
 * 
 * @param string $url        The URL
 * @param array  $query_vars Additional query parameters in associate array
 * @return string
 */
function legacy_urls_prepare_url($url, array $query_vars = array()) {
	$params = array();
	// Elgg munges the request in htaccess rules so cannot use $_GET
	$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
	if ($query) {
		parse_str($query, $params);
	}
	$params = array_merge($params, $query_vars);
	if ($params) {
		return elgg_http_add_url_query_elements($url, $params);		
	} else {
		return $url;
	}
}

/**
 * Handle requests for /tag/<tag string>
 */
function legacy_urls_tag_handler($segments) {
	$tag = $segments[0];
	$url = legacy_urls_prepare_url('search', array('q' => $tag));
	return legacy_urls_redirect($url);
}

/**
 * Handle requests for URLs that start with /pg/
 */
function legacy_urls_pg_handler($segments) {
	$url = implode('/', $segments);
	return legacy_urls_redirect(legacy_urls_prepare_url($url));
}

/**
 * Blog forwarder
 * 
 * 1.0-1.7.5
 * Group blogs page: /blog/group:<container_guid>/
 * Group blog view:  /blog/group:<container_guid>/read/<guid>/<title>
 * 1.7.5-pre 1.8
 * Group blogs page: /blog/owner/group:<container_guid>/
 * Group blog view:  /blog/read/<guid>
 */
function legacy_url_blog_forward($hook, $type, $result) {

	$page = $result['segments'];

	// easier to work with & no notices
	$page = array_pad($page, 4, "");

	// group usernames
	if (preg_match('~/group\:([0-9]+)/~', "/{$page[0]}/{$page[1]}/", $matches)) {
		$guid = $matches[1];
		$entity = get_entity($guid);
		if (elgg_instanceof($entity, 'group')) {
			if (!empty($page[2])) {
				$url = "blog/view/$page[2]/";
			} else {
				$url = "blog/group/$guid/all";
			}
			// we drop query params because the old group URLs were invalid
			legacy_urls_redirect(legacy_urls_prepare_url($url));
			return false;
		}
	}

	if (empty($page[0])) {
		return;
	}

	if ($page[0] == "read") {
		$url = "blog/view/{$page[1]}/";
		legacy_urls_redirect(legacy_urls_prepare_url($url));
		return false;		
	}

	// user usernames
	$user = get_user_by_username($page[0]);
	if (!$user) {
		return;
	}

	if (empty($page[1])) {
		$page[1] = 'owner';
	}

	switch ($page[1]) {
		case "read":
			$url = "blog/view/{$page[2]}/{$page[3]}";
			break;
		case "archive":
			$url = "blog/archive/{$page[0]}/{$page[2]}/{$page[3]}";
			break;
		case "friends":
			$url = "blog/friends/{$page[0]}";
			break;
		case "new":
			$url = "blog/add/$user->guid";
			break;
		case "owner":
			$url = "blog/owner/{$page[0]}";
			break;
	}

	if (isset($url)) {
		legacy_urls_redirect(legacy_urls_prepare_url($url));
		return false;
	}
}
