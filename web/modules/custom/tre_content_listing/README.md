Work in progress

#### Paragraph type: List Content Listing

- High level category: sets the taxonomy that the listing is based off from (eg "Sports")
- Filter type: sets filtering type as alphabetical or hierarchical (taxonomy based)
- Allowed filter groups: sets the allowed filter groups (eg "Age", "Education background")

#### Content type: Listing content

- used to define content nodes that are listed under sub-terms
- Listing keyword: sets the related sub-term category (eg. Football club could have it as "Football")
- Filter options: applied filters for the content type (eg. 6-12 if the club is targeted for that age range)

#### Taxonomy vocabulary: Content list keywords

- Defines high level terms and their sub terms that the listings are based off from. Example hierarchy
- Example hierarchy

```
Content Listing Terms
├── Sports
│   ├── Football
│   ├── Basketball
│   └── Tennis
└── City Services
    ├── Healthcare
    ├── Education
    └── Transportation
```

#### Taxonomy vocabulary: Filter groups

- Defines the filter groups for List Content Listing paragraph
- Example hierarchy

```
Filter groups (Vocabulary)
├── Age
│   ├── 6-12
│   ├── 12-18
│   └── 18-25
└── Education background
    ├── Elementary school
    ├── High shcool
    └── Bachelors degree
```

#### Listing controller / JSON api endpoint

Currently the controller can be used to fetch direct child terms under specified parent term OR to fetch nodes created under specific sub term.

atm filtering is made so that:

- alphabetical filters can be applied to sub-terms.
- hieararchical filters can be applied to nodes under sub terms.

pagination does not work currently

---

```
api/tre-content-listing/{term_id}/sub-terms
```

Lists all sub terms under the parent term id and total amount of matching nodes. Works with filters

Example response:

```
{
  "parent_term": "Sports",
  "parent_tid": 5426,
  "sub_terms": [
    {
      "term_name": "Baseball",
      "term_id": "5431",
      "matching_items": 1
    },
    {
      "term_name": "Football",
      "term_id": "5430",
      "matching_items": 2
    }
  ]
}
```

Can also be queried with alphabetical filters using letter(s).

```
api/tre-content-listing/{term_id}/sub-terms?filter_type=alphabetical&letter[]=K
```

---

```
api/tre-content-listing/{sub_term_id}/sub-term-items
```

Lists all nodes under the sub-term.

Example response:

```
{
  "term_id": "5430",
  "term_name": "Football",
  "filter_type": "alphabetical",
  "items": [
    {
      "id": "78887",
      "title": "Mansester United"
    },
    {
      "id": "78889",
      "title": "Elite football club"
    }
  ],
  "page": 0,
  "limit": 10
}
```

Can also be queried with hierarchical filters. Filter options can be multiple, so eg. with multiple different age options.

```
api/tre-content-listing/{sub_term_id}/sub-term-items?filter_type=hierarchical&age[]=25-35
```
