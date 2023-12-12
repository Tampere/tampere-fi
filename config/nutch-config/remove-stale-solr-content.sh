#!/usr/bin/env bash

SOLR_URL="${1:-http://tampere.l:8985/solr/external}"

# Outputs HTTP status code for given URL.
function statusCode() {
  local url="${1}"
  curl --head --location --connect-timeout 5 --write-out %{http_code} --silent --output /dev/null "${url}"
}

# Checks whether given HTTP status code represents an HTTP error.
# Special case to adjust for cURL's special 000 status code which may result from a dysfunctional call to a URL.
function errorCode() {
  local status="${1}"
  local code="${2}"
  case $code in
  500)
    [[ "${status}" == "000" ]] || [[ "${status}" == "${code}" ]]
    ;;
  *)
    [[ "${status}" == "${code}" ]]
    ;;
  esac
}

# Outputs a CSV with external URLs to check from the KADA solr core. Also adds a column for the document id.
function fetchAllExternalDocsFromSolr() {
  local filename="${1}"
  local statusUrl=$(printf '%s/select?fq=ss_type:www3.jkl.fi&rows=0' "${SOLR_URL}")
  local status=$(statusCode "${statusUrl}")
  errorCode "${status}" 500 && echo "Error connecting to Solr (500)" && exit 1
  errorCode "${status}" 404 && echo "Error connecting to Solr (404)" && exit 1

  local externaDocsUrl=$(printf '%s/select?q=*.*&wt=csv&csv.separator=|&csv.header=false&fl=id&rows=10000000' "${SOLR_URL}")
  curl --silent "${externaDocsUrl}" >"${filename}"
}

# Loops through the document given, expecting rows in the format <url>|<id>.
# Issues deletions for the documents where the URL gives a status code starting with 4.
function removeStaleDocsFromSolr() {
  local filename="${1}"
  while read url; do
    local status=$(statusCode "${url}")
    case $status in
    3*)
      echo "redirect: ${status} ${url}"
      ;;
    4*)
      echo "${status} ${url}"
      deleteDocFromSolr "${url}"
      ;;
    5*)
      echo "error: ${status} ${url}"
      ;;
    esac

  done <"${filename}"
}

# Deletes a document id. URL-encodes the id to get Solr to accept it as part of an XML input.
# Commits the deletion(s that might be queued) if the other argument is left out (or given as 'yes').
function deleteDocFromSolr() {
  local docid=$(echo "${1}" | sed 's/:/\\\\:/g;')
  local query=$(printf '{ "delete": { "query": "id:%s" }, "commit": {} }' "${docid}")
  local post_command=$(printf "curl -X POST --silent '%s/update' --data-binary '%s'" "${SOLR_URL}" "${query}")
  echo "Running command: ${post_command}"
  eval "${post_command}"
}

# Wrapper function for the process.
function processExternalDocsFromSolr() {
  local filename="/tmp/solr_external_docs_list_$(date +%Y%m%d-%H%M%S).csv"
  echo "Using temp file ${filename} for removal..."
  fetchAllExternalDocsFromSolr "${filename}"
  removeStaleDocsFromSolr "${filename}"
}

processExternalDocsFromSolr
