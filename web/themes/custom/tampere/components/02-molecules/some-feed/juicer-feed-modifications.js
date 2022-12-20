Drupal.tampere = Drupal.tampere || {};

// Changes dates from Juicer's preferred format to 'd.m.Y' format.
Drupal.tampere.formatSocialMediaFeedDates = function () {

  const socialMediaFeeds = document.querySelectorAll('.juicer-feed');

  if (socialMediaFeeds.length) {
    socialMediaFeeds.forEach((socialMediaFeed) => {
      const postTimes = socialMediaFeed.querySelectorAll('.j-date');

      postTimes.forEach((postTime) => {
        const date = new Date(postTime.getAttribute('datetime'));
        const day = date.getDate();
        const month = date.getMonth() + 1;
        const year = date.getFullYear();

        postTime.textContent = `${day}.${month}.${year}`;
      });
    });
  }
};

// Changes the default Juicer read more texts.
Drupal.tampere.changeReadMoreText = function () {

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
    });
  }

  const socialMediaFeedReadMoreLinks = document.querySelectorAll('.juicer-feed .j-read-more');
  const pageLanguage = document.documentElement.lang;
  const finnishText = 'Lue lisää';
  const englishText = 'Read more';

  if (socialMediaFeedReadMoreLinks.length) {
    replaceFeedReadMoreTexts(socialMediaFeedReadMoreLinks, pageLanguage === 'fi' ? finnishText : englishText);
  }
};

// Swaps the text content and image in Twitter posts to match with designs better.
Drupal.tampere.changeTwitterPostStructure = function () {
  const tweets = document.querySelectorAll('.juicer-feed .feed-item.j-twitter');

  if (tweets.length) {
    tweets.forEach((tweet) => {
      const image = tweet.querySelector('.j-image');

      if (image && image.previousElementSibling) {
        image.parentNode.insertBefore(image, image.previousElementSibling);
      }
    });
  }
};

// Translates the Load more button on Finnish pages.
Drupal.tampere.translateLoadMoreButton = function () {
  const loadMoreButtons = document.querySelectorAll('.juicer-feed .j-paginate');
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
  Drupal.tampere.formatSocialMediaFeedDates();
  Drupal.tampere.changeReadMoreText();
  Drupal.tampere.changeTwitterPostStructure();
  Drupal.tampere.translateLoadMoreButton();
};
