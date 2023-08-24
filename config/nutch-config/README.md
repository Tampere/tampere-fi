# Basic information

The configuration directories in this directory are modified from Nutch 1.19 default configuration.

# Languages and environments

There is one config directory per language and environment: for each of local, development (dev), staging (stg), and production (prod) environments there is a separate config directory for each of the languages English (en) and (Finnish) (fi).

Each language is meant to be run using a separate seed file, urls_fi/seed.txt for Finnish and urls_en/seed.txt for English.

Note! Running Nutch requires that you have the package downloaded and unzipped, plus that you have Java (>= 11) installed and the JAVA_HOME environment variable set correctly.

# Examples

## Local example

In my personal local environment the current valid setting for JAVA_HOME is:

```bash
export JAVA_HOME=/opt/homebrew/Cellar/openjdk@11/11.0.18/libexec/openjdk.jdk/Contents/Home
```

Consider an example where you have extracted Nutch 1.19 inside this nutch-config directory in a directory called 'nutch'. In that case the correct way to run the scraping and indexing for Finnish content (for 2 rounds of fetches) on local environment is:
```bash
NUTCH_CONF_DIR=conf_local_fi nutch/bin/crawl -s urls_fi -i crawl_local_fi 2
```

## Server example

On the server environments it is probably wiser to separate the indexing step from the crawldb creation step and use the first command as a one-time operation per seed file / language, and the latter two commands in a repeated manner. Note that each of the commands in the example uses the 'fi' language, so a separate set of commands is needed for the corresponding 'en' language.

Consider an example where the Nutch configuration has been installed under `/opt` directory:
```bash
# This command injects the seeds into the crawldb, only has to be run once unless reseeding is needed. The urls_fi content from this repo has been copied to /opt/nutch/urls/dev_fi.
NUTCH_CONF_DIR=/opt/nutch/conf/dev_fi bin/nutch inject /opt/nutch/crawls/dev_fi/crawldb /opt/nutch/urls/dev_fi
# This command crawls 5 rounds of links and stores the results in the local file system.
NUTCH_CONF_DIR=/opt/nutch/conf/dev_fi bin/crawl /opt/nutch/crawls/dev_fi 5
# This command indexes into Solr the results of the crawls, removing pages no longer in the crawldb.
NUTCH_CONF_DIR=/opt/nutch/conf/dev_fi bin/nutch index /opt/nutch/crawls/dev_fi/crawldb -linkdb /opt/nutch/crawls/dev_fi/linkdb /opt/nutch/crawls/dev_fi/segments/* -deleteGone
```

# Setup on server(s)

## Recommended server layout

* `/opt/nutch`: the root directory for the setup where the archive for nutch 1.19 should be extracted.
* `/opt/nutch/conf/*`: the language and environment-specific configuration directories (dev_fi, dev_en etc).
* `/opt/nutch/urls/*`: the language and environment-specific seed directories (dev_fi, dev_en etc).
* `/opt/nutch/crawls/*`: the language and environment-specific crawldb directories. Will contain a subdirectory for each language-environment combination (dev_fi, dev_en etc).
* `/opt/nutch/crawls/*/crawldb`: the crawldb for each language-environment combination.
* `/opt/nutch/crawls/*/linkdb`: the linkdb for each language-environment combination.
* `/opt/nutch/crawls/*/segments`: the segmented crawl results for each language-environment combination.

For the scripts to run optimally, a script should be run to create the directories needed and to copy the configs into place:

```bash
#!/bin/bash
# Init script for Nutch configuration.
SERVERS=(
  dev
  stg
  prod
)
LANGUAGES=(
  fi
  en
)
CRAWLDIRS=(
  crawldb
  linkdb
  segments
)
for language in ${LANGUAGES[@]}; do
  mkdir -p "/opt/nutch/urls/$language";
  for server in ${SERVERS[@]}; do
    mkdir -p "/opt/nutch/conf/${server}_${language}";
    mkdir -p "/var/log/nutch/${server}_${language}";
    for subdir in ${CRAWLDIRS[@]}; do
      mkdir -p "/opt/nutch/crawls/${server}_${language}/${subdir}";
    done;
  done;
done

for language in ${LANGUAGES[@]}; do
  rsync -r "urls_${language}/" "/opt/nutch/urls/$language/";
  for server in ${SERVERS[@]}; do
    rsync -r "conf_${server}_${language}/" "/opt/nutch/conf/${server}_${language}/";
  done;
done
```

The above code will create the recommended directory layout where the configuration has been synced into place, given that the script is run _from the directory where this README file resides_.

So in practice: make a new script file, e.g. called `setup.sh`, in this directory, containing the code above, then run it using `bash setup.sh`.

## Recommended cron jobs

### Before setting up the cron job

For example for the dev, fi crawldb, run:
```bash
NUTCH_CONF_DIR=/opt/nutch/conf/dev_fi bin/nutch inject /opt/nutch/crawls/dev_fi/crawldb /opt/nutch/urls/fi
```

### A note in general about the cron jobs

It is worth noting that the actual command runs the crawl first and the indexing after that. The NUTCH_CONF and NUTCH_CONF_DIR are declared separately because combining them proved unsuccessful in testing.

### Recommended cron job for dev, fi
```bash
MAILTO=""
NUTCH_CONF="dev_fi"
NUTCH_CONF_DIR="/opt/nutch/conf/dev_fi"
NUTCH_LOG_DIR="/var/log/nutch/dev_fi"
5 4 * * * root  /opt/nutch/bin/crawl "/opt/nutch/crawls/${NUTCH_CONF}" 5; /opt/nutch/bin/nutch index "/opt/nutch/crawls/${NUTCH_CONF}/crawldb" -linkdb "/opt/nutch/crawls/${NUTCH_CONF}/linkdb" "/opt/nutch/crawls/${NUTCH_CONF}/segments"/* -deleteGone -filter
```

### Recommended cron job for dev, en

Same as for "dev, fi" except dev_fi should be replaced with dev_en and the timing could be different.

### Recommended cron job for stg, fi

Same as for "dev, fi" except dev_fi should be replaced with stg_fi and the timing could be different.

### Recommended cron job for stg, en

Same as for "dev, fi" except dev_fi should be replaced with stg_en and the timing could be different.

### Recommended cron job for prod, fi

Same as for "dev, fi" except dev_fi should be replaced with prod_fi and the timing could be different.

### Recommended cron job for prod, en

Same as for "dev, fi" except dev_fi should be replaced with prod_en and the timing could be different.

## What happens when a seed.txt file changes?

As with all other config changes, you should rerun the init script to deploy the configuration. See above for the script `Init script for Nutch configuration`.

After the new configuration is in place, you should rerun the inject script for the environment in question. See section 'Before setting up the cron job'.

Note! When changing URLs in the seed.txt, you will also need to update the corresponding regex-urlfilter.txt.

### Addition of new site in seed.txt

No special attention is needed in this case; the subsequent cralws and index runs should take the addition into account.

### Removal of a site from seed.txt

To stop crawling a site, the changes should be recorded in:
* seed.txt (for the correct language)
* regex-urlfilter.txt (for the correct server-language pairing)

Note that it is not enough to just remove the URL from the regex allowing certain URLs (at the bottom of regex-urlfilter.txt), but instead you should also add a negation line for it after the `+` line, for example for valoviikot.tampere.fi:

```
-^https?://valoviikot\.tampere\.fi
```

When a site is removed from seed.txt, more often than not its content should be removed from the index with immediate effect. Since the `id` field contains the URL, you can formulate a query against Solr to delete documents with those values in the field.

For example for the local environment, when removing e.g. valoviikot.tampere.fi content, the `curl` command would look like this:
```bash
curl -X POST -H 'Content-Type: application/json' 'http://tampere.l:8985/solr/external/update' --data-binary '
{
  "delete": { "query": "id:*//valoviikot.tampere.fi/*" }, "commit":{}
}'

```
Note that the query value does not have the protocol marker ('https:') simply because it's difficult to encode a colon (`:`) into a format that would be universally accepted by Solr _in the value_.

#### What happens if a domain is removed from seed.txt in one language but not from the other?

You should do the removal of the content from Solr as stated above. But also you can re-index the remaining language by issuing the correct indexing command if you want to speed up the process of getting back the URLs for the remaining content. They will reappear correctly though even without further action, it just will take some time.

An example of an indexing command that will honor the URL filter changes:
```bash
NUTCH_CONF="dev_fi" /opt/nutch/bin/nutch index "/opt/nutch/crawls/${NUTCH_CONF}/crawldb" -linkdb "/opt/nutch/crawls/${NUTCH_CONF}/linkdb" "/opt/nutch/crawls/${NUTCH_CONF}/segments"/* -deleteGone -filter
```
