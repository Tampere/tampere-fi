((Drupal) => {
  "use strict";

  Drupal.behaviors.eventzTodayHydration = {
    attach: function (context) {
      context
        .querySelectorAll(".eventz-liftups-container:not(.processed)")
        .forEach(async (container) => {
          container.classList.add("processed");
          const paragraphId = container.dataset.paragraphId;
          // Sanity check to make sure we have paragraph to hydarate.
          if (!paragraphId) {
            return;
          }

          // Get the default error message that was attached by the preprocess.
          const fallbackMessage =
            window.drupalSettings?.eventzToday?.errorMessages?.[paragraphId] ||
            "Problems in fetching events.";

          try {
            // Fetch paragraph data from controller.
            const response = await fetch(`/api/eventz-today/${paragraphId}`);
            let data = null;

            try {
              data = await response.json();
            } catch (e) {
              // If the data is not valid json -> throw the default error message.
              throw new Error(fallbackMessage);
            }

            if (!response.ok || !data.success) {
              // In case the response from controller doesnt
              // contain a valid error message, use the fallback.
              throw new Error(data?.message || fallbackMessage);
            }

            // Get the liftup container.
            const liftupContainer = container.querySelector(".eventz-liftups-content");
            if (!liftupContainer) {
              console.error(
                "Hydration error: Could not find '.topical-listing__liftups'"
              );
              return;
            }

            // Clear any existing content.
            liftupContainer.innerHTML = "";

            // Hydrate featured liftup if it exists in the data.
            if (data.featured_liftup_exists && data.featured_liftup) {
              const featuredCard = this.createFeaturedCard(
                data.featured_liftup
              );
              liftupContainer.appendChild(featuredCard);
            }

            // Create regular event cards from fetched data.
            data.liftups.forEach((eventData) => {
              const cardWrapper = this.createEventCard(eventData);
              liftupContainer.appendChild(cardWrapper);
            });

            // Hydrate topical listing link if it exists.
            if (
              data.topical_listing_link &&
              data.topical_listing_link.length > 0
            ) {
              // Get the parent listing element that contains this liftup container.
              const topicalListing = container.closest(".topical-listing");
              if (topicalListing) {
                const topicalListingWrapper = topicalListing.querySelector(
                  ".topical-listing__wrapper"
                );
                // Once we have found the wrapper for this contaniner, we can add the links.
                if (topicalListingWrapper) {
                  let linkListContainer =
                    topicalListingWrapper.querySelector(".link-list");

                  if (!linkListContainer) {
                    // Create link list container if it doesnt exist
                    const linkListWrapper = document.createElement("div");
                    linkListWrapper.className = "topical-listing__link";

                    linkListContainer = document.createElement("ul");
                    linkListContainer.className =
                      "link-list link-list--center link-list--more-space";

                    linkListWrapper.appendChild(linkListContainer);
                    topicalListingWrapper.appendChild(linkListWrapper);
                  } else {
                    // Clear existing links
                    linkListContainer.innerHTML = "";
                  }

                  // Add the topical listing links
                  data.topical_listing_link.forEach((linkData) => {
                    const linkItem = document.createElement("li");
                    linkItem.className = "link-list__item";

                    const link = document.createElement("a");
                    link.className = "link-list__link";
                    link.href = linkData.url;
                    link.textContent = linkData.text;

                    if (linkData.icon) {
                      const svgIcon = this.createExternalLinkIcon(
                        linkData.icon,
                        "link-list__icon"
                      );
                      link.appendChild(svgIcon);
                    }

                    linkItem.appendChild(link);
                    linkListContainer.appendChild(linkItem);
                  });
                }
              }
            }

            // Hide loading spinner element and show content.
            container.classList.add("is-hydrated");
          } catch (error) {
            console.error("Error during hydration:", error);
            // Error -> remove the loading spinner, add an error class.
            container.classList.add("has-error");

            // Display error message in the message list component
            const topicalListing = container.closest(".topical-listing");
            if (topicalListing) {
              const topicalListingWrapper = topicalListing.querySelector(
                ".topical-listing__wrapper"
              );
              if (topicalListingWrapper) {
                // Find or create the status container.
                let statusContainer = topicalListingWrapper.querySelector(".status");

                // If the status container doesn't exist, create one.
                if (!statusContainer) {
                  statusContainer = document.createElement("div");
                  statusContainer.className = "status status--error";

                  // Insert before the eventz liftups container
                  topicalListingWrapper.insertBefore(
                    statusContainer,
                    container
                  );
                } else {
                  // Clear existing messages and add error class
                  statusContainer.innerHTML = "";
                  statusContainer.classList.add("status--error");
                }

                // Create error message element
                const errorList = document.createElement("ul");
                errorList.className = "status__list";

                const errorItem = document.createElement("li");
                errorItem.className = "status__list-item";
                errorItem.textContent = fallbackMessage;

                errorList.appendChild(errorItem);
                statusContainer.appendChild(errorList);
              }
            }
          }
        });
    },

    // ...existing code...

    /**
     * Creates an external link SVG icon.
     */
    createExternalLinkIcon: function (
      iconName = "external",
      additionalClass = ""
    ) {
      const svgIcon = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "svg"
      );

      const classes = ["icon", "external-link__icon", "rs_skip"];
      if (additionalClass) {
        classes.push(additionalClass);
      }

      svgIcon.setAttribute("class", classes.join(" "));
      svgIcon.setAttribute("role", "img");
      svgIcon.setAttribute(
        "aria-label",
        (Drupal.t && Drupal.t("Linkki ulkoiselle sivulle")) ||
          "Link to external website"
      );

      const useElement = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "use"
      );
      useElement.setAttributeNS(
        "http://www.w3.org/1999/xlink",
        "xlink:href",
        `/themes/custom/tampere/dist/main-site-icons.svg#${iconName}`
      );
      svgIcon.appendChild(useElement);

      return svgIcon;
    },

    /**
     * Creates a featured event card element.
     */
    createFeaturedCard: function (featuredData) {
      const cardWrapper = document.createElement("li");
      cardWrapper.className =
        "topical-card__wrapper topical-card__wrapper--big";

      const cardLink = document.createElement("a");
      cardLink.className = "topical-card topical-card--event topical-card--big";
      cardLink.href = featuredData.url;

      // Content wrapper
      const contentWrapper = document.createElement("div");
      contentWrapper.className = "topical-card__content";

      // Heading
      const heading = document.createElement("h3");
      heading.className = "topical-card__heading h3 hyphenate";
      heading.textContent = featuredData.name;
      contentWrapper.appendChild(heading);

      // Summary
      if (featuredData.summary) {
        const summary = document.createElement("p");
        summary.className = "topical-card__summary";
        summary.textContent = featuredData.summary;
        contentWrapper.appendChild(summary);
      }

      // Date and location.
      if (featuredData.date || featuredData.location) {
        const details = document.createElement("div");
        details.className = "topical-card__details";
        const parts = [];
        if (featuredData.date) parts.push(featuredData.date);
        if (featuredData.location) parts.push(featuredData.location);
        details.textContent = parts.join(" | ");
        contentWrapper.appendChild(details);
      }

      // External link icon
      const svgIcon = this.createExternalLinkIcon(
        "external",
        "topical-card__icon"
      );
      contentWrapper.appendChild(svgIcon);

      cardLink.appendChild(contentWrapper);

      // Image
      if (featuredData.image_src) {
        const imgContainer = document.createElement("div");
        imgContainer.className = "topical-card__image-container";
        imgContainer.setAttribute("aria-hidden", "true");
        imgContainer.innerHTML = `<img class="image__img image__img--" loading="lazy" alt="" src="${featuredData.image_src}">`;
        cardLink.appendChild(imgContainer);
      }

      cardWrapper.appendChild(cardLink);
      return cardWrapper;
    },

    /**
     * Creates a regular event card element.
     */
    createEventCard: function (eventData) {
      const cardWrapper = document.createElement("li");
      cardWrapper.className = "topical-card__wrapper";

      const cardLink = document.createElement("a");
      cardLink.className = "topical-card topical-card--alt";
      cardLink.href = eventData.link_url;

      // Construct the text wrapper.
      const textWrapper = document.createElement("div");
      textWrapper.className = "topical-card__content";

      // Add heading to the text wrapper.
      const heading = document.createElement("h3");
      heading.className = "topical-card__heading h3 hyphenate";
      heading.textContent = eventData.heading;
      textWrapper.appendChild(heading);

      // Add event time to the text wrapper.
      if (eventData.date || eventData.tag) {
        const details = document.createElement("div");
        details.className = "topical-card__details";
        const parts = [];
        if (eventData.date) parts.push(eventData.date);
        if (eventData.tag) parts.push(eventData.tag);
        details.textContent = parts.join(" | ");
        textWrapper.appendChild(details);
      }

      // Create SVG with use element
      const svgIcon = this.createExternalLinkIcon(
        "external",
        "topical-card__icon"
      );
      textWrapper.appendChild(svgIcon);
      cardLink.appendChild(textWrapper);

      // Create image container.
      if (eventData.image_src) {
        const imgContainer = document.createElement("div");
        imgContainer.className = "topical-card__image-container";
        imgContainer.setAttribute("aria-hidden", "true");
        imgContainer.innerHTML = `<img class="image__img image__img--" loading="lazy" alt="" src="${eventData.image_src}">`;
        cardLink.appendChild(imgContainer);
      }

      cardWrapper.appendChild(cardLink);
      return cardWrapper;
    },
  };
})(Drupal);