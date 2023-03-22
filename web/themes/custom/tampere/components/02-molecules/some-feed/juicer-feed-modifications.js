Drupal.tampere = Drupal.tampere || {};

// Changes dates from Juicer's preferred format to 'd.m.Y' format.
Drupal.tampere.formatJuicerSocialMediaFeedDates = function (feed) {
  const postTimes = feed.querySelectorAll('.j-date:not(.js-date-modified)');

  postTimes.forEach((postTime) => {
    const date = new Date(postTime.getAttribute('datetime'));
    const day = date.getDate();
    const month = date.getMonth() + 1;
    const year = date.getFullYear();

    postTime.textContent = `${day}.${month}.${year}`;
    postTime.classList.add('js-date-modified');
  });
};

// Changes the default Juicer read more texts.
Drupal.tampere.changeReadMoreText = function (feed) {

  /**
   * Replaces the read more texts in links given as parameter.
   *
   * @param {NodeList} readMoreLinks
   * @param string newText
   *   The new read more text to use.
   */
  function replaceFeedReadMoreTexts(readMoreLinks, newText) {
    readMoreLinks.forEach((link) => {
      link.textContent = newText;
      link.classList.add('.js-read-more-modified');
    });
  }

  const socialMediaFeedReadMoreLinks = feed.querySelectorAll('.j-read-more:not(.js-read-more-modified)');
  const pageLanguage = document.documentElement.lang;
  const finnishText = 'Lue lisää';
  const englishText = 'Read more';

  if (socialMediaFeedReadMoreLinks.length) {
    replaceFeedReadMoreTexts(socialMediaFeedReadMoreLinks, pageLanguage === 'fi' ? finnishText : englishText);
  }
};

Drupal.tampere.changeJuicerMetaStructure = function (feed) {

  /**
   * Replaces the aria label for a social link.
   *
   * Originally the social media links just include the name
   * of the platform even though they link to the individual
   * post page.
   *
   * @param {HTMLElement} socialLink
   */
  function replaceSocialLinkAriaLabel(socialLink) {
    // Not relying on the current aria label in case Juicer
    // changes it in the future. Getting the source from the parent feed item.
    const parentFeedItem = socialLink.closest('.feed-item');
    const postSource = parentFeedItem.getAttribute('data-source');
    const pageLanguage = document.documentElement.lang;

    socialLink.setAttribute('aria-label', pageLanguage === 'fi'
      ? `Avaa alkuperäinen julkaisu palvelussa ${postSource}`
      : `Open original post on ${postSource}`);
  }

  const metaElements = feed.querySelectorAll('.j-meta:not(.js-meta-modified)');

  metaElements.forEach((metaElem) => {
    const navElement = metaElem.querySelector('nav');
    const socialLink = metaElem.querySelector('.j-social');

    // Removing the unnecessary 'nav' wrapper.
    navElement.replaceWith(...navElement.childNodes);

    replaceSocialLinkAriaLabel(socialLink);

    metaElem.querySelector('ul').remove();
    metaElem.classList.add('js-meta-modified');
  });
};

// Swaps the text content and image in Twitter posts to match with designs better.
Drupal.tampere.changeTwitterPostStructure = function (feed) {
  const tweets = feed.querySelectorAll('.feed-item.j-twitter:not(.js-twitter-feed-item-modified)');

  if (tweets.length) {
    tweets.forEach((tweet) => {
      const image = tweet.querySelector('.j-image');

      if (image && image.previousElementSibling) {
        image.parentNode.insertBefore(image, image.previousElementSibling);
        tweet.classList.add('js-twitter-feed-item-modified');
      }
    });
  }
};

// Sets all images other than the post content images as decorative.
Drupal.tampere.setJuicerImagesAsDecorative = function (feed) {
  const feedImages = feed.querySelectorAll('img:not(.js-decorative):not(.j-content-image)');

  feedImages.forEach((feedImage) => {
    feedImage.setAttribute('alt', '');
    feedImage.classList.add('js-decorative');
  });
};

Drupal.tampere.setJuicerProfileNameElements = function (feed) {
  const postProfileNames = feed.querySelectorAll('h3');

  postProfileNames.forEach((profileNameElem) => {
    const nameWrapper = document.createElement('span');
    nameWrapper.innerText = profileNameElem.innerText;
    nameWrapper.classList.add('profile-name');
    profileNameElem.replaceWith(nameWrapper);
  });
};

// Translates the Load more button on Finnish feeds.
Drupal.tampere.translateLoadMoreButton = function (feed) {
  const loadMoreButtons = feed.querySelectorAll('.j-paginate');
  const pageLanguage = document.documentElement.lang;
  const finnishText = 'Lataa lisää';

  if (loadMoreButtons.length && pageLanguage === 'fi') {
    loadMoreButtons.forEach((loadMoreButton) => {
      loadMoreButton.textContent = finnishText;
    });
  }
};

// This is called in each Juicer feed.
Drupal.tampere.formatSocialMediaFeed = function () {
  const socialMediaFeeds = document.querySelectorAll('.juicer-feed');

  if (!socialMediaFeeds.length) {
    return;
  }

  socialMediaFeeds.forEach((socialMediaFeed) => {
    Drupal.tampere.changeTwitterPostStructure(socialMediaFeed);
    Drupal.tampere.changeJuicerMetaStructure(socialMediaFeed);
    Drupal.tampere.changeReadMoreText(socialMediaFeed);
    Drupal.tampere.formatJuicerSocialMediaFeedDates(socialMediaFeed);
    Drupal.tampere.setJuicerProfileNameElements(socialMediaFeed);
    Drupal.tampere.setJuicerImagesAsDecorative(socialMediaFeed);
    Drupal.tampere.translateLoadMoreButton(socialMediaFeed);
  });
};
