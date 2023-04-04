/**
 * @file
 * A JavaScript file containing the table functionality.
 *
 */

Drupal.behaviors.table = {
  attach(context) {
    const tables = once('table', '.table', context);

    tables?.forEach((table) => {
      const tableId = table.getAttribute('id');
      const responsiveTable = new ResponsiveTable(`#${tableId}`, 'stack', '800px');
    });
  },
};
