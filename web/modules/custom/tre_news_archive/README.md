TRE News Archive
================

This module handles news saving to pdf and archiving
automatically once a year.

There is process to save the news of last year, and archiving news 
from the year before last. These are handled once a year after new years eve.

## Commands

There is drush command created to test this module. When the module is
enabled you can run `ddev drush save-news` and it will save the news of
last year to pdf file and you can see it on news_archive view in content page.

Second drush command is for testing the archiving functionality. It will
archive all the news from the year before the last. It can be triggered
with `ddev drush archive-news`.
