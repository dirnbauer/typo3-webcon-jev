/**
 * webcon_jev: deleting a decision, after asking — from the list and from the editor alike.
 */
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import labels from '~labels/webcon_jev.module';
import { postJson } from '@webconsulting/webcon-jev/api.js';

/**
 * Ask whether the decision should go — naming the forms and conditions that still use it — and
 * delete it on yes.
 *
 * @param {{url: string, uid: number, title: string, forms?: number, rules?: number}} decision
 * @returns {Promise<boolean>} true once the decision is gone
 */
export function confirmAndDelete({ url, uid, title, forms = 0, rules = 0 }) {
  return new Promise((resolve) => {
    let answered = false;
    let confirmed = false;
    const answer = (value) => {
      if (!answered) {
        answered = true;
        resolve(value);
      }
    };

    const usage = forms > 0 || rules > 0 ? ' ' + labels.get('delete.usage', { forms, rules }) : '';
    const modal = Modal.confirm(
      labels.get('delete.title'),
      labels.get('delete.message', [title]) + usage,
      SeverityEnum.warning,
      [
        {
          text: labels.get('delete.cancel'),
          active: true,
          btnClass: 'btn-default',
          name: 'cancel',
          trigger: (event, dialog) => {
            dialog.hideModal();
            answer(false);
          },
        },
        {
          text: labels.get('delete.confirm'),
          btnClass: 'btn-warning',
          name: 'delete',
          trigger: async (event, dialog) => {
            confirmed = true;
            dialog.hideModal();
            answer(await remove(url, uid));
          },
        },
      ],
    );
    // Escape, the close button or a click on the backdrop answer "no". The dialog also closes
    // after a "yes", while the request is still under way, so that must not count as a "no".
    modal.addEventListener('typo3-modal-hidden', () => {
      if (!confirmed) {
        answer(false);
      }
    });
  });
}

async function remove(url, uid) {
  let result;
  try {
    result = await postJson(url, { uid });
  } catch (error) {
    Notification.error(labels.get('delete.failed'), String(error?.message ?? error));

    return false;
  }

  if (result.data.ok) {
    Notification.success(labels.get('delete.done'), result.data.message ?? '');

    return true;
  }

  Notification.error(labels.get('delete.failed'), result.data.message ?? labels.get('error.status', [result.status]));

  return false;
}
