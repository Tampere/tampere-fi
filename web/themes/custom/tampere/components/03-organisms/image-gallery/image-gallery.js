/**
 * @file
 * A JavaScript file containing the image gallery functionality.
 *
 * Initializes a Tobii instance if one does not already exist for
 * the page. Can be used with any component requiring the lightbox
 * functionality.
 *
 */

/**
 * Returns the caption for the element given as parameter.
 *
 * @param {HTMLElement} currentElement
 *   The element to get the caption for.
 *
 * @returns {string}
 */
function getCaption(currentElement) {
  const galleryItem = currentElement.closest('.image-gallery__item');
  let caption = "";

  if (!galleryItem) {
    return caption;
  }

  const captionElement = galleryItem.querySelector('.image-gallery__caption');

  if (captionElement) {
    caption = captionElement.innerText;
  }

  return caption;
}

/**
 * Returns the item name for the element given as parameter.
 *
 * @param {HTMLElement} currentElement
 *   The element to get the item name for.
 *
 * @returns {string}
 */
 function getItemName(currentElement) {
  const galleryItem = currentElement.closest('.image-gallery__item');
  let name = "";

  if (!galleryItem) {
    return name;
  }

  const nameElement = galleryItem.querySelector('.image-gallery__item-name');

  if (nameElement) {
    name = nameElement.innerText;
  }

  return name;
}

Drupal.behaviors.imageGallery = {
  attach(context) {
    const closeIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke="none" d="M0 0h24v24H0z"></path><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
    const imageGalleries = once('image-gallery', '.image-gallery', context);
    const tobiiOnceId = 'tobii';
    let imageGalleryImages;
    let parentLink;

    if (imageGalleries) {
      imageGalleries.forEach((gallery) => {
        imageGalleryImages = once('image-gallery-images', 'img', gallery);

        imageGalleryImages.forEach((galleryImg) => {
          const itemName = getItemName(galleryImg);
          parentLink = galleryImg.parentNode;
          parentLink.classList.add('lightbox');
          parentLink.dataset.group = gallery.id;
          parentLink.setAttribute('aria-label', Drupal.t('Show image @name in a larger size', { '@name': itemName }));
        });
      });
    }

    let tobiiOnced = once.find(tobiiOnceId);
    if (tobiiOnced.length === 0) {
      const tobii = new Tobii({
        captionText: getCaption,
        navLabel: [Drupal.t('Previous image'), Drupal.t('Next image')],
        closeText: `${Drupal.t('Close window')} ${closeIcon}`,
        loadingIndicatorLabel: Drupal.t('Image loading'),
      });

      tobiiOnced = once(tobiiOnceId, document.body, context);
    }

    if (tobiiOnced) {
      const tobiiOncedElement = tobiiOnced.shift();
      const tobiiCloseButton = tobiiOncedElement.querySelector('.tobii__btn--close', context);

      if (tobiiCloseButton) {
        // Remove unnecessary aria-label from Tobii close button as the library
        // always adds an aria label even when the button contains discernible text.
        tobiiCloseButton.removeAttribute('aria-label');
      }
    }
  },
};
