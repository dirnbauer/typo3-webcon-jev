/**
 * webcon_jev: the decision list's delete buttons.
 */
import { confirmAndDelete } from '@webconsulting/webcon-jev/delete-decision.js';

class DecisionList {
  constructor() {
    document.addEventListener('click', (event) => {
      const button = event.target.closest?.('[data-webcon-jev-delete]');
      if (button instanceof HTMLButtonElement) {
        event.preventDefault();
        this.delete(button);
      }
    });
  }

  async delete(button) {
    const table = button.closest('[data-webcon-jev-decisions]');
    const row = button.closest('tr');
    const gone = await confirmAndDelete({
      url: table.dataset.deleteUrl,
      uid: Number(button.dataset.webconJevDelete),
      title: button.dataset.title,
      forms: Number(button.dataset.forms) || 0,
      rules: Number(button.dataset.rules) || 0,
    });

    if (!gone) {
      button.focus();

      return;
    }

    const neighbour = row.nextElementSibling ?? row.previousElementSibling;
    row.remove();
    if (table.querySelector('tbody tr') === null) {
      // The empty state, with its "create" action, is rendered by the server.
      window.location.reload();

      return;
    }
    neighbour?.querySelector('a[href], button')?.focus();
  }
}

export default new DecisionList();
