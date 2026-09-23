/**
 * webcon_jev: talking to the module's AJAX endpoints.
 */
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import { AjaxResponse } from '@typo3/core/ajax/ajax-response.js';

/**
 * POST a JSON body and hand back the status and the decoded answer.
 *
 * AjaxRequest rejects on every status outside 2xx. The endpoints answer a refused save with 422
 * and a body that is exactly what the caller needs (which field, which problem), so a rejection
 * is unwrapped here and reads like any other answer. Only a network failure still throws.
 *
 * @param {string} url
 * @param {object} body
 * @returns {Promise<{status: number, data: object}>}
 */
export async function postJson(url, body) {
  try {
    const response = await new AjaxRequest(url).post(body, {
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
    });

    return { status: response.raw().status, data: await response.resolve('json') };
  } catch (error) {
    if (!(error instanceof AjaxResponse)) {
      throw error;
    }

    let data = null;
    try {
      data = await error.resolve('json');
    } catch {
      // An HTML error page from a proxy, an empty body: nothing to unwrap.
    }

    return {
      status: error.raw().status,
      data: data !== null && typeof data === 'object' ? data : { ok: false },
    };
  }
}
