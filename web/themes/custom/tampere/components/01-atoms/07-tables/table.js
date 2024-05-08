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
      // eslint-disable-next-line
      const responsiveTable = new ResponsiveTable(`#${tableId}`, 'stack', '800px');
    });
  },
};
