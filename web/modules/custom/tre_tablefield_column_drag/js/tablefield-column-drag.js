/**
 * @file
 * A JavaScript file containing the drag functionality of tablefield columns.
 *
 */

/**
 * Handles the functionality of on drop item.
 *
 * @param {Event} event
 *   The event of the draggedItem drop.
 */
function handleDragDrop(event) {
  event.preventDefault();

  const index = event.currentTarget.index;

  let columnItems = event.currentTarget.tableBody.getElementsByClassName('dragged');
  const dragElementRowChildren = event.currentTarget.dragElementRow.children;
  let dragStartIndex;
  for (let i = 0; i < dragElementRowChildren.length; i++) {
    if (dragElementRowChildren[i] === columnItems[0]) {
      dragStartIndex = i;
    }
  }

  event.currentTarget.tableRows.forEach((row) => {
    const rowChildren = row.children;
    let draggedItemValue = rowChildren[dragStartIndex].getElementsByTagName('input')[0].value;

    if (dragStartIndex > index) {
      for (let i = dragStartIndex; i > index; i--) {
        let newValue = rowChildren[i - 1].getElementsByTagName('input')[0].value;
        rowChildren[i].getElementsByTagName('input')[0].value = newValue;
      }
      rowChildren[index].getElementsByTagName('input')[0].value = draggedItemValue;

      columnItems[0].classList.remove('dragged');
    } else {
      for (let i = dragStartIndex; i < index; i++) {
        let newValue = rowChildren[i + 1].getElementsByTagName('input')[0].value;
        rowChildren[i].getElementsByTagName('input')[0].value = newValue;
      }
      rowChildren[index].getElementsByTagName('input')[0].value = draggedItemValue;
    }
  });

  columnItems[0].classList.remove('dragged');
}

/**
 * Creates the element of the row where column drag items live.
 *
 * @param {HTMLElement} tbody
 *   The table body element where the new element is going to be added.
 * @param {HTMLElement} tableRows
 *   The table row elements that contains the elements which are dragged.
 * @param {Number} rowLength
 *   The table row length.
 */
function createDragElementRow(tbody, tableRows, rowLength) {
  const dragElementRow = document.createElement('tr');
  // tableRowItems.forEach((rowItem, index) => {
  for (let index = 0; index < rowLength; index++) {
    if (index !== 0 && index !== 1) {
      const dragRowItem = document.createElement('td');
      dragRowItem.classList.add(`dragger-${index}`);
      const dragLink = document.createElement('a');
      dragLink.href = '#';
      dragLink.classList.add('tabledrag-handle');
      const divHandle = document.createElement('div');
      divHandle.classList.add('handle');
      dragLink.appendChild(divHandle);
      dragRowItem.appendChild(dragLink);

      dragRowItem.addEventListener('dragstart', (event) => {
        // Add classname to dragged elements
        let dragElementRowChildren = dragElementRow.children;
        let draggingElement = dragElementRowChildren[index];
        draggingElement.classList.add('dragged');

        tableRows.forEach(row => {
          const children = row.children;
          let item = children[index];
          item.classList.add('dragged');
        })
      });
      dragRowItem.addEventListener('dragover', (event) => {
        // prevent default to allow drop
        event.preventDefault();
      }, false);
      dragRowItem.index = index;
      dragRowItem.tableBody = tbody;
      dragRowItem.tableRows = tableRows;
      dragRowItem.dragElementRow = dragElementRow;
      dragRowItem.addEventListener('drop', handleDragDrop);

      dragElementRow.appendChild(dragRowItem);
    } else {
      const dragRowItem = document.createElement('td');
      dragElementRow.appendChild(dragRowItem);
      if (index === 1) {
        dragRowItem.style.display = 'none';
      }
    }
  }
  return dragElementRow;
}

Drupal.behaviors.columnDrag = {
  attach(context, drupalSettings) {
    let paragraphTitles = document.querySelectorAll(
      '.paragraph-type-title',
      context
    );

    if (!paragraphTitles && paragraphTitles.length === 0) {
      return;
    }

    paragraphTitles.forEach(paragraphTitle => {
      // Check that title is target label (table paragraph)
      if (paragraphTitle.textContent === drupalSettings.tampere.tablefieldColumnDragTargetLabel) {
        let tableSubform = paragraphTitle.parentElement.nextElementSibling;
        if (tableSubform !== null) {
          // eslint-disable-next-line no-undef
          let tbody = once(
            'table-paragraph-tbody',
            tableSubform.querySelector(
              'tbody',
              context
            )
          )[0];
          // eslint-disable-next-line no-undef
          let tableRows = once(
            'table-paragraph-row',
            tableSubform.querySelectorAll(
              'tr',
              context
            )
          );
          if (tableRows[0] !== undefined && tbody !== undefined) {
            // eslint-disable-next-line no-undef
            let tableRowItems = once(
              'table-paragraph-row-item',
              tableRows[0].querySelectorAll(
                'td',
                context
              )
            );
            const dragElementRow = createDragElementRow(tbody, tableRows, tableRowItems.length);
            
            tbody.insertBefore(dragElementRow, tbody.firstChild);
          }
        }
      }
    });
  }
}