import React from "react";
import { useTranslation } from "react-i18next";
import { treDrupalSettings } from "../config";

const serviceLinks = [
  {
    id: 'contact',
    translationKey: 'search_service_search_link_1_text',
    urls: {
      fi: 'https://www.tampere.fi/yhteystietohaku',
      en: 'https://www.tampere.fi/en/contact-search',
    },
    iconName: '?20261105#arrow',
  },
  {
    id: 'events',
    translationKey: 'search_service_search_link_2_text',
    urls: {
      fi: 'https://tapahtumat.tampere.fi',
      en: 'https://tapahtumat.tampere.fi/en-FI',
    },
    iconName: '?20261105#external',
  },
  {
    id: 'agenda-minutes',
    translationKey: 'search_service_search_link_3_text',
    urls: {
      fi: 'https://tampere.cloudnc.fi',
      en: 'https://tampere.cloudnc.fi/fi-FI',
    },
    iconName: '?20261105#external',
  },
];

const CludoSearchServices = () => {
  const { t } = useTranslation();

  return (
    <div className="search-service__container search-service__container">
      <h2 className="search-service__container__title">
        {t("translation:search_services:search_service_title")}
      </h2>
      <div className="search-service__container__overview">
        <div className="introduction">
          <p className="introduction__text">
            {t("translation:search_services:search_service_introduction_text")}:
          </p>
        </div>
        <div className="service__directory">
          <ul className="list">
            {serviceLinks.map((link) => {
              const {id, translationKey, urls, iconName} = link;
              const currentUrl = treDrupalSettings.currentLanguage === 'fi' ? urls.fi : urls.en;
              return (
                <li key={id} className="list-item">
                  <a href={currentUrl} className="list-item-link">
                    <div className="link-content">
                      <div className="content-heading-container">
                        <p className="content-heading-text">
                          {t(`translation:search_services:${translationKey}`)}
                        </p>
                      </div>
                      <svg className="content-icon rs_skip" aria-hidden="true" role="presentation">
                        <use href={`/themes/custom/tampere/dist/main-site-icons.svg${iconName}`} />
                      </svg>
                    </div>
                  </a>
                </li>
              );
            })}
          </ul>
        </div>
      </div>
    </div>
  )
}
export default CludoSearchServices;