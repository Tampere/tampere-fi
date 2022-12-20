/**
 * @file
 * A JavaScript file containing the image gallery functionality.
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
  const captionElement = currentElement.closest('.image-gallery__item')
    .querySelector('.image-gallery__caption');
  let caption;

  if (captionElement) {
    caption = captionElement.innerText;

    if (caption) {
      return caption;
    }
  }

  return "";
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
  const nameElement = currentElement.closest('.image-gallery__item')
    .querySelector('.image-gallery__item-name');
  let name;

  if (nameElement) {
    name = nameElement.innerText;

    if (name) {
      return name;
    }
  }

  return "";
}

Drupal.behaviors.imageGallery = {
  attach(context) {
    const imageGalleries = once('image-gallery', '.image-gallery', context);
    const tobiiOnceId = 'tobii';
    let imageGalleryImages;
    let parentLink;
    let tobii;
    let tobiiOncedElement;

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

      tobiiOncedElement = once.find(tobiiOnceId);
      if (tobiiOncedElement.length === 0) {
        tobii = new Tobii({
          captionText: getCaption,
          navLabel: [Drupal.t('Previous image'), Drupal.t('Next image')],
          closeLabel: Drupal.t('Close'),
          loadingIndicatorLabel: Drupal.t('Image loading'),
        });

        tobiiOncedElement = once(tobiiOnceId, document.body, context).shift();
      }
    }
  },
};
