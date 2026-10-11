/**
 * REST helper: every call goes to nhrrob-issue-reporter/v1.
 */
import apiFetch from '@wordpress/api-fetch';

const ROOT = 'nhrrob-issue-reporter/v1';

/**
 * @param {string} path   Route path, e.g. '/state'.
 * @param {string} method HTTP method.
 * @param {Object} data   JSON body.
 * @return {Promise<Object>} Parsed response.
 */
export default function api( path, method = 'GET', data ) {
	return apiFetch( { path: ROOT + path, method, data } );
}
