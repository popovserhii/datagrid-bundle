# GridSchema contract version 1

This document describes the JSON emitted by `Popov\DatagridBundle\Schema\GridSchema`. It describes the declared column types and structure, together with presentation, interaction, and renderer configuration. It contains metadata, not row values.

`Popov\DatagridBundle\Schema\ElasticsearchMapping` consumes this contract to build an Elasticsearch mapping for **row documents**. Column types are declarations made by the Grid developer. Integration starts with defaults: unknown types use the `string` rule, and generic arrays/JSON use Elasticsearch's dynamic mapping. The mapper does not inspect Doctrine metadata, SQL, field names, or sample values. Renderer-specific options are optional refinements, not a prerequisite for producing a mapping.

## Input and output

`GridSchema::create()` accepts either a `ZfcDatagrid\Datagrid` instance or an object with `getDatagrid()` that returns one. It returns a PHP array. `createJson()` JSON-encodes that array with `JSON_THROW_ON_ERROR`, `JSON_UNESCAPED_UNICODE`, and `JSON_UNESCAPED_SLASHES`.

Values passed through from `Datagrid`, actions, or `renderer_parameters` must have a meaningful JSON representation. Encoding errors such as resources or recursive references cause `createJson()` to throw `JsonException`; ordinary objects may encode only their public properties.

The top-level structure is always:

```json
{
  "schema_version": "1.0",
  "grid": {},
  "columns": []
}
```

`schema_version` is currently the literal string `"1.0"`. It identifies the contract; `ColumnMapping` does not require or validate this field.

## Grid object

`grid` is always an object with these fields.

| Field | JSON type | Meaning |
| --- | --- | --- |
| `id` | string | Grid identifier from `Datagrid::getId()`. The underlying library supplies `"defaultGrid"` when none was set. |
| `title` | string | Display title. It is not translated by `GridSchema`. |
| `default_items_per_page` | integer | Default page size. A negative value may be used by the grid implementation for an unlimited result set. |
| `filterable` | boolean | Whether the grid-level user filter UI is enabled. |
| `parameters` | object or array | Arbitrary external grid parameters, passed through unchanged. Its keys and value types are not a stable schema contract. |
| `row_styles` | array of style objects | Styles applied to a whole row. The style object contract is defined below. |
| `row_click_action` | action object or `null` | The action to execute when a row is clicked. |
| `has_mass_actions` | boolean | Only indicates whether at least one mass action exists. Details such as mass-action title, link, and confirmation are not exported. |

## Column list and ordering

`columns` is always a JSON array. `GridSchema` calls `Datagrid::sortColumns()` before exporting it, so columns are ordered by their configured `position`. This call mutates the `Datagrid` instance's internal column order. Columns with the same position retain the ordering provided by the underlying positions collection.

For child columns, the exporter currently uses `ObjectColumn::getColumns()` without sorting again. Child order is whatever the column currently holds. Elasticsearch property order has no significance.

Every exported column is an object with the following fields.

| Field | JSON type | Meaning |
| --- | --- | --- |
| `id` | string | `AbstractColumn::getUniqueId()`. This is the canonical column reference used by styles and action conditions. It can differ from the original source field name, for example `user_id`. |
| `label` | string | Display label without translation. |
| `type` | type object | Declared logical type, with display settings for supported types. Its shape depends on `type.name`. |
| `columns` | array of column objects; optional | Child fields of an `ObjectColumn` or `CollectionColumn`, exported recursively with the same column contract. |
| `position` | integer or `null` | Configured display position. Columns added through `Datagrid::addColumn()` normally receive the default position `1`. |
| `width` | number | Display width, expressed by ZfcDatagrid as a percentage-like relative value. |
| `hidden` | boolean | Column visibility. |
| `skipped` | boolean | Whether the column is omitted from a response. |
| `identity` | boolean | Whether the column is an identity column. The underlying API normally also hides an identity column. |
| `row_click_enabled` | boolean | Whether this column participates in the row-click behavior. |
| `sortable` | boolean | Whether the user may sort by this column. |
| `sort` | object | Default and active sorting state. |
| `filter` | object | User filtering configuration and active value. |
| `translation_enabled` | boolean | Whether cell values are translated by the grid. |
| `replace_values` | object, array, or `null` | Value replacement map when configured; otherwise `null`. |
| `styles` | array of style objects | Styles applied to this column's cells. |
| `actions` | array of action objects | Actions for a `Column\Action`; otherwise an empty array. See the current implementation issue below. |
| `renderer_parameters` | object | Arbitrary parameters grouped by renderer contract, passed through unchanged. |

### Sort object

```json
{
  "default": {
    "priority": 1,
    "sortDirection": "ASC"
  },
  "active_direction": "DESC"
}
```

`sort.default` is either `null` or an object with `priority` (integer) and `sortDirection` (normally `ASC` or `DESC`). `sort.active_direction` is either `null` or a direction string. `sortDirection` is inherited from ZfcDatagrid and intentionally remains camel-cased in version 1.

### Filter object

```json
{
  "enabled": true,
  "default_value": null,
  "default_operator": "~ *%s*",
  "select_options": null,
  "active_value": null
}
```

| Field | JSON type | Meaning |
| --- | --- | --- |
| `enabled` | boolean | Whether users may filter the column. |
| `default_value` | string or `null` | Configured default filter value. |
| `default_operator` | string | ZfcDatagrid filter expression, such as `"= %s"`, `">= %s"`, or `"~ *%s*"`. This is a grid filter DSL, not an Elasticsearch operator. |
| `select_options` | object, array, or `null` | Option labels/values when the filter is a select filter. Both sequential and associative arrays are valid. |
| `active_value` | string or `null` | Current active filter value. |

The available filter expressions include equality, inequality, comparison, like/not-like, inclusion, range, and null checks. An Elasticsearch adapter must translate these expressions explicitly; copying the expression into a query is not valid.

## Type object

`type` is always an object with at least `name`.

| `type.name` | Additional fields currently exported | Meaning |
| --- | --- | --- |
| `string` | none | `PhpString`; the default type. |
| `number` | `style`, `notation`, `locale`, `prefix`, `suffix` | Number display configuration. `style` and `notation` are emitted only for known `NumberFormatter` styles. |
| `dateTime` | `format`, `timezone`, `locale` | Date and time display configuration. |
| `array` | none | `PhpArray` and `JsonableArray` both export this same name. |
| `object` | none | `JsonObject`: one structured object, with its fields described by the column's sibling `columns` array. |
| `collection` | none | `JsonCollection`: a collection of structured objects. The sibling `columns` array describes each element. |
| `image` | none | Image presentation type. |
| custom name | none unless `GridSchema::type()` is extended | Any custom `AbstractType::getTypeName()` value. |

For `number`:

- `style` is one of `decimal`, `currency`, or `percent`; a scientific formatter exports `style: "decimal"` and `notation: "scientific"`.
- `notation` is `standard` for the other recognized styles.
- `locale` is converted from a simple ICU locale such as `uk_UA` to a BCP 47-like value such as `uk-UA`; complex ICU locale identifiers are kept unchanged.
- `prefix` and `suffix` are always strings, including an empty string.

For `dateTime`:

- `format` is an ICU date/time pattern. It is the explicit output pattern when configured, otherwise a pattern generated by `IntlDateFormatter`.
- `timezone` is the output timezone and should be an IANA timezone identifier.
- `locale` follows the same normalization rule as `number.locale`.

`type.name` is the developer's declared type. The mapper automatically uses `dateTime.format` and `dateTime.locale` for an Elasticsearch date field. Common ICU patterns such as `yyyy-MM-dd` and `yyyy-MM-dd HH:mm:ss` are compatible with ES and are copied as declared. The complete ICU and Java pattern languages are not interchangeable; a specialized format can be refined through `renderer_parameters.elasticSearch.mapping.format`. Number display precision does not determine the numeric storage type, and numeric display settings are not copied to ES mapping parameters.

The exporter does not include `Number` patterns and formatter attributes, `DateTime` source format and source timezone, `PhpArray` separators, image resize settings, or a generic array element type. These are not prerequisites for mapping `object` and `collection`.

### Structured columns

| Column class | Default type class | `type.name` | Elasticsearch default |
| --- | --- | --- | --- |
| `ObjectColumn` | `JsonObject` | `object` | `object` |
| `CollectionColumn` | `JsonCollection` | `collection` | `nested` |

Both columns provide their default type through `getType()`, independently of SQL generation. A developer can explicitly set a type with `setType()`. `CollectionColumn` extends `ObjectColumn`, so the existing recursive export handles both. The mapper reads the exported names; it has no dependency on these PHP classes.

```json
{
  "id": "user_roles",
  "type": { "name": "collection" },
  "renderer_parameters": { "elasticSearch": { "field": "roles" } },
  "columns": [
    {
      "id": "role_id",
      "type": { "name": "string" },
      "renderer_parameters": { "elasticSearch": { "field": "id" } }
    },
    {
      "id": "role_details",
      "type": { "name": "object" },
      "renderer_parameters": { "elasticSearch": { "field": "details" } },
      "columns": [
        {
          "id": "details_title",
          "type": { "name": "string" },
          "renderer_parameters": { "elasticSearch": { "field": "title" } }
        }
      ]
    }
  ]
}
```

This abbreviated column metadata describes document data such as:

```json
{ "roles": [{ "id": "admin", "details": { "title": "Administrator" } }] }
```

`collection` already means a collection of objects; no `items` wrapper or `collection: true` flag is needed. Each nested object or collection uses the same recursive column shape.

The schema does not guarantee non-null values, element ordering, uniqueness, or a minimum collection size. The current SQL implementations can return `null` for missing related data. Value normalization belongs to document preparation, independently of mapping generation.

## Styles

A style object has this common shape:

```json
{
  "type": "color",
  "conditions_operator": "OR",
  "conditions": [
    {
      "column": "user_status",
      "operator": "= %s",
      "value": "inactive"
    }
  ]
}
```

`conditions_operator` is `OR` or `AND`. Each condition has a left-side grid-column ID, a ZfcDatagrid filter expression, and a value. When the right-hand side is another `AbstractColumn`, the exported value is a reference:

```json
{ "column": "user_status" }
```

Otherwise it is passed through as the literal value. This makes `conditions[].value` polymorphic: it can be a scalar, array, object, or a column-reference object.

Additional properties depend on the style class:

| Style class | Current `type` value | Additional property |
| --- | --- | --- |
| `Align` | `align` | `alignment`: `left`, `right`, `center`, or `justify` |
| `Color` | `color` | `color`: `#rrggbb` |
| `BackgroundColor` | `backgroundColor` | `color`: `#rrggbb` |
| `CSSClass` | `cSSClass` | `class`: CSS class string |
| `Bold` | `bold` | none |
| `Italic` | `italic` | none |
| `Strikethrough` | `strikethrough` | none |
| `Html` | `html` | none |

The unusual `cSSClass` spelling is an actual version-1 output: it comes from `lcfirst('CSSClass')`. Consumers must support it until the schema version changes or a compatibility alias is introduced.

## Actions

The intended action object shape is:

```json
{
  "type": "button",
  "attributes": {
    "href": "/users/:rowId:",
    "class": "btn btn-default"
  },
  "route": "user_show",
  "route_params": { "id": ":rowId:" },
  "visibility_operator": "OR",
  "visibility_conditions": [],
  "label": "Open"
}
```

All actions export `type`, `attributes`, `route`, `route_params`, `visibility_operator`, and `visibility_conditions`. Visibility conditions use the same structure as style conditions. `Button` adds `label`, which is either a string or `{ "column": "column_id" }`. `Icon` adds `icon_class` and `icon_link`. `Checkbox` has no additional exported field, so its configured checkbox name is not represented. Placeholder dependencies in an action link are also not exported separately.

### Current implementation issue

`row_click_action` is exported correctly because `GridSchema::action()` currently accepts a `Datagrid` and reads its row-click action. Column actions are not exportable in the current source: `actions()` passes an `AbstractAction` into that same method, which is declared as `action(Datagrid $datagrid)`. A grid containing a `Column\Action` with at least one action therefore throws `TypeError` during schema creation. This is an upstream export issue; the mapping converter cannot fix a schema that was not produced.

Before treating `actions` as part of the final contract, split this into two methods, for example `rowClickAction(Datagrid $datagrid)` and `action(?AbstractAction $action)`. Then add tests for `Button`, `Icon`, `Checkbox`, visibility conditions, and row-click actions.

## Renderer parameters

`renderer_parameters` has this form:

```json
{
  "jqGrid": {
    "formatter": "currencyFormatter",
    "classes": "amount"
  },
  "elasticSearch": {
    "field": "amount",
    "mapping": {
      "type": "scaled_float",
      "scaling_factor": 100
    }
  }
}
```

The first-level key is the renderer contract name and is case-sensitive. The nested value is copied without normalization. It may contain renderer-specific JavaScript fragments, CSS values, field names, or arbitrary custom options. `GridSchema` uses reflection because ZfcDatagrid exposes only one named renderer contract through its public API. The optional `getAllRendererParameters()` branch is only a forward-compatibility hook; it is not part of the current ZfcDatagrid API.

This field must be treated as opaque by generic consumers. A renderer-specific adapter is responsible for interpreting it. It must not be used as a source of trusted executable code.

## Information deliberately absent from version 1

The following are not in the output and cannot be reconstructed by a consumer:

- formatter definitions; the earlier `formatters` field is no longer emitted;
- actual grid row data, data-source details, query, renderer name, paginator state, and authorization;
- full mass-action definitions;
- `notReplacedGetEmpty` for replacement values;
- type settings listed in the Type section;
- action placeholder-column list and checkbox name.

## Elasticsearch mapping

```php
use Popov\DatagridBundle\Schema\GridSchema;
use Popov\DatagridBundle\Schema\ColumnMapping;

$schema = (new GridSchema())->create($grid);
$mapper = new ColumnMapping();
$mapping = $mapper->create($schema);
$json = $mapper->createJson($schema, JSON_PRETTY_PRINT);

// Contents of an index-creation request; no HTTP request is made by the mapper.
$body = ['mappings' => $mapping];
```

`create()` accepts the array returned by `GridSchema::create()` or `json_decode($json, true, 512, JSON_THROW_ON_ERROR)`. It reads `columns` and returns the contents of the ES `mappings` object, without an index name or a legacy mapping-type wrapper. Missing `columns` produces an empty properties object. Generated `properties` maps are PHP `stdClass` objects so empty maps and numeric field names serialize as JSON objects. The outer result and field definitions are PHP arrays.

The structured example above produces:

```json
{
  "properties": {
    "roles": {
      "type": "nested",
      "properties": {
        "id": { "type": "keyword" },
        "details": {
          "type": "object",
          "properties": {
            "title": { "type": "keyword" }
          }
        }
      }
    }
  }
}
```

`collection → nested` is this adapter's explicit policy: it preserves the relationship between fields of each collection element during nested queries. It follows the Elasticsearch [nested field model](https://www.elastic.co/docs/reference/elasticsearch/mapping-reference/nested). `object → object` remains a direct conversion.

### Type rules

| Declared `type.name` | Default Elasticsearch type |
| --- | --- |
| `object` | `object`, with recursive `properties` |
| `collection` | `nested`, with recursive `properties` |
| `string` | `keyword` |
| `number` | `double` |
| `dateTime` | `date` |
| `boolean`, `integer`, `long`, `float`, `double`, `keyword`, `text`, `date` | Same name |
| `array` | No explicit field mapping; Elasticsearch maps the array/JSON from incoming documents |
| `image`, an unregistered custom type, or a missing type | Same rule as `string` (`keyword` by default) |

`string → keyword` and `number → double` are fixed defaults, not inferences from labels, filter controls, identity flags, or formatting. Unknown types also follow the configured `string` rule, including constructor overrides. Full-text search can be configured with an explicit `text` mapping; no extra multi-fields or analyzers are added automatically to explicitly mapped fields. The mapper trusts declarations without querying the data source or requiring the developer to finish every type definition first.

Extend or replace type rules without changing the mapper:

```php
$mapper = new ElasticsearchMapping([
    'string' => 'text',
    'money' => 'scaled_float',
    'uuid' => 'keyword',
    'ip' => 'ip',
]);
```

Rules map names to ES type names. A `null` rule delegates the field to Elasticsearch's normal dynamic mapping; this is the default for `array`. Additional parameters such as `scaling_factor` belong to the optional per-column ES contract. A per-column `mapping.type` takes precedence over the type table and can name any Elasticsearch type supported by the target cluster.

### Standard type parameters

The mapper builds each field in this order: resolve the ES type, take the standard schema parameters supported by that type, then apply the explicit ES options. `TYPE_PARAMETERS` lists the supported parameters; `typeParameters()` reads them from the schema. This requires no extra configuration on a Grid column.

| Resolved Elasticsearch type | Standard parameters copied from `column.type` |
| --- | --- |
| `date`, `date_nanos` | Non-empty `format` and `locale` |
| Other types | None currently |

For example, this abbreviated schema column needs no `renderer_parameters`:

```json
{
  "id": "created",
  "type": {
    "name": "dateTime",
    "format": "yyyy-MM-dd HH:mm:ss",
    "locale": "uk-UA",
    "timezone": "Europe/Kyiv"
  }
}
```

Its mapping is:

```json
{
  "type": "date",
  "format": "yyyy-MM-dd HH:mm:ss",
  "locale": "uk-UA"
}
```

The same rule applies to fields inside `object` and `collection`. Explicit `mapping.format` or `mapping.locale` replaces the corresponding standard value. If `mapping.type` changes the field to `keyword`, no date parameters are inherited. `date_nanos` can be selected through a type rule or a per-column type override and receives the same parameters.

The project's explicit Grid output patterns (`yyyy-MM-dd` and `yyyy-MM-dd HH:mm:ss`) are shared by ICU and [Elasticsearch's Java date syntax](https://www.elastic.co/docs/reference/elasticsearch/mapping-reference/mapping-date-format). The converter does not rewrite arbitrary ICU patterns or quoted literals. It copies a supplied pattern; specialized ICU constructs and differing localized text can require an explicit native ES format. Missing or empty standard parameters are omitted, leaving the [Elasticsearch defaults](https://www.elastic.co/docs/reference/elasticsearch/mapping-reference/date).

`timezone` is not a date field mapping parameter and is not emitted into the mapping. Use it during document preparation when interpreting a local date without an offset, then emit a date with an offset or normalize it to UTC. An [ES date processor](https://www.elastic.co/docs/reference/ingest-processor/date-processor) is another place that supports timezone-aware parsing. Adding a timezone to schema metadata alone does not convert a value or configure Elasticsearch's date parser.

Numeric `style`, `notation`, `locale`, `prefix`, and `suffix` describe display formatting. They have no direct equivalent in numeric field mappings. If formatted Grid output is used as the indexing source, document preparation must supply the numeric value represented by that output. The current mapper produces field definitions and does not transform document values.

The date format must also describe the representation actually indexed. Ordinary Grid columns run through `getUserValue()`, whereas `ObjectColumn` builds JSON in SQL and its JSON type only decodes that value; it does not apply each child column's formatter. Preparing nested values consistently remains the document producer's responsibility.

### The `elasticSearch` contract

`renderer_parameters.elasticSearch` is entirely optional. Without it, the mapper uses the identity naming rule below, type rules, and supported standard type parameters. When fine-tuning is needed, its keys are:

| Key | Meaning |
| --- | --- |
| `field` | Optional document field name relative to the containing object. Overrides the name resolved from `id` and `identity`. |
| `exclude` | `true` omits the column and its subtree from the generated mapping. |
| `mapping` | Native Elasticsearch field definition. Explicit keys replace defaults without recursive array merging. |

Configure it directly on a Grid column:

```php
$column
    ->setRendererParameter('field', 'title', 'elasticSearch')
    ->setRendererParameter('mapping', [
        'type' => 'text',
        'analyzer' => 'standard',
        'fields' => ['raw' => ['type' => 'keyword']],
    ], 'elasticSearch');
```

This does not change what `GridSchema` exports: it still copies all renderer contracts unchanged. Only `ColumnMapping` interprets `elasticSearch`. Other contracts, such as `jqGrid`, are ignored by this converter.

The converter adds recursive `properties` only for `object` and `nested`. An explicit `mapping.properties` is authoritative and replaces the generated child definitions entirely. This is useful when a developer supplies a complete native object mapping. An explicit `mapping.type` can also override the default `collection → nested` policy, for example with `object` or `flattened`. For other ES types, child columns are not converted into `properties`.

`mapping` parameters such as `fields`, `analyzer`, `normalizer`, `format`, `index`, `doc_values`, `null_value`, `dynamic`, `copy_to`, and `scaling_factor` are passed through. Version-specific validity and required parameters remain the developer's and Elasticsearch's responsibility. Use JSON objects for object-valued native parameters; in PHP an empty JSON object must be represented by `(object) []`.

### Names, dates, arrays, and exclusions

- **Field names:** the contract uses `alias_field`, with no underscores in `alias`. The mapper splits only at the first underscore, preserving names such as `display_name`. The root identity column determines which fields belong directly to the document; see Identity and filter aliases below. Child fields inside `object` and `collection` use the part after the first underscore, matching `ObjectColumn` output. Explicit `elasticSearch.field` always takes precedence. Dotted names are passed through for Elasticsearch to interpret; the mapper does not reconcile overlapping dotted paths.
- **Dates:** `dateTime → date` automatically copies non-empty `type.format` and `type.locale`. They can be overridden through the optional ES contract. `type.timezone` belongs to document preparation, not the field mapping; see Standard type parameters above.
- **Arrays and JSON:** Elasticsearch has [no separate array type](https://www.elastic.co/docs/reference/elasticsearch/mapping-reference/array). For `type.name: "array"`, the converter leaves the field out of the explicit `properties` map. The field's value remains in the indexed document, where Elasticsearch's default [dynamic mapping](https://www.elastic.co/docs/manage-data/data-store/mapping/dynamic-field-mapping) handles scalar arrays or JSON objects. No element type or renderer parameter is required to start. JSON supplied as a string remains a string unless document preparation decodes it. If a fixed mapping is wanted later, add a type rule or `mapping.type`, for example `keyword`, `long`, or `object`; other per-field mapping options are applied once a type is supplied. Dynamic mapping must remain enabled in the containing object for automatic mapping to occur. Use `collection` with child `columns` when nested object semantics are needed.
- **UI metadata:** visibility, sorting, filtering, styles, actions, and value replacements do not determine ES storage settings. Hidden fields are included. `identity: true` determines the parent alias for field names and filter aliases; it does not become Elasticsearch `_id`. Document identity is set during indexing.
- **Exclusions:** `skipped: true` and `elasticSearch.exclude: true` omit a subtree from the explicit mapping. Omission is different from `mapping.index: false`. With Elasticsearch's default dynamic mapping, an omitted field can still be mapped if the indexing code sends it. Document preparation controls which values are actually sent. The converter does not enable `dynamic: strict`.

The converter adds no schema-validation exceptions. Missing or unknown types use the string default; nameless columns are skipped. When multiple columns generate an explicit mapping for the same field, the later definition replaces the earlier one. Explicit native mapping options are passed through without structural validation. Correct types and compatible options remain the developer's responsibility and can be refined incrementally. This does not replace Elasticsearch's own validation of mappings and documents.

### Identity and filter aliases

The first root column with `identity: true` and an `alias_field` ID supplies the parent alias. Its position among the columns does not affect the result. An excluded or skipped identity column still identifies its group, even though the field itself is not mapped. Multiple identity columns belonging to the same alias are supported by this rule.

For example, `status_id` with `identity: true` selects `status`. The mapper writes `status_id`, `status_title`, and `status_color` as physical fields `id`, `title`, and `color`, and adds filter aliases:

```json
{
  "properties": {
    "id": { "type": "keyword" },
    "title": { "type": "keyword" },
    "color": { "type": "keyword" },
    "status.id": { "type": "alias", "path": "id" },
    "status.title": { "type": "alias", "path": "title" },
    "status.color": { "type": "alias", "path": "color" }
  }
}
```

These aliases belong to the mapping; the source document remains flat for the parent fields. Other root aliases keep their object prefix: with parent alias `project`, `projectGroup_id` maps to `projectGroup.id`. A structured column `project_projectGroup` maps to the object `projectGroup`, and its children use relative field names. Neither case adds a `project` wrapper to the document or to the child paths.

Generated aliases apply only to concrete parent fields. Objects and nested collections retain their existing paths; their fields are not lifted into root aliases. A dynamic `array` has no declared target field, so it gets no generated alias. An explicit field rename changes the alias target, for example `status.title → caption`. An alias is omitted if its name already equals the physical field name. Explicit field definitions take precedence over generated aliases with the same key.

Root columns without an identity alias keep the previous default of using their IDs verbatim. Child columns inside structured fields still use relative names. Alias detection does not fall back to the first column or to `grid.id`. No new fields are required in `GridSchema`.

Elasticsearch [field aliases](https://www.elastic.co/docs/reference/elasticsearch/mapping-reference/field-alias) support searches, including filters and sorting, but do not rename source document keys. Aliases cannot target objects or other aliases and must share the target's nested scope.

### Index configurations

`IndexMapping` wraps `ColumnMapping` to prepare an index-creation request or a list of requests. It keeps the existing one-schema mapping API intact:

```php
use Popov\DatagridBundle\Schema\IndexMapping;

$builder = new IndexMapping($mapper);
$index = $builder->create($schema);

$schemas = json_decode($schemasJson, true, 512, JSON_THROW_ON_ERROR);
$indices = $builder->createMany($schemas, [
    'projectGroup' => [
        'index' => 'project-group',
        'settings' => ['number_of_replicas' => 1],
    ],
]);

$json = $builder->createManyJson($schemas, [], JSON_PRETTY_PRINT);
```

Each result has `index` and `body.mappings`, matching the [PHP client's index-creation parameters](https://www.elastic.co/docs/reference/elasticsearch/clients/php/index_management). One schema produces one configuration; `createMany()` preserves input order. It does not combine schemas targeting the same index.

The default index name is `strtolower(grid.id)`. The original `grid.id`, column names and filter aliases retain their casing. Options for `createMany()` are keyed by the original `grid.id`, such as `projectGroup`.

| Option | Meaning |
| --- | --- |
| `index` | Explicit index name, passed through unchanged. |
| `settings` | Native index settings, included only when supplied. |
| `aliases` | Native index aliases, included only when supplied. These are separate from generated field aliases. |
| `mappings` | Optional root mapping parameters. Explicit keys override generated defaults; explicit `properties` replaces the generated properties entirely. |

No index settings or index aliases are invented. Use JSON objects for object-valued native options, for example `'aliases' => ['current-status' => (object) []]`. Valid and unique index names remain the caller's responsibility.

`createJson($schema, $options, $flags)` and `createManyJson($schemas, $options, $flags)` serialize the corresponding results using the same JSON flags as `ColumnMapping`.

Both classes only prepare configurations. Queries, HTTP requests, index creation and document transformation remain with the caller. Indexing schema metadata itself is a different task and is not performed by these converters.

## Possible additions to the standard schema

`object`, `collection`, recursive `columns`, and renderer contracts are sufficient for the current mapper. No additional standard fields are required for those types, and no extra fields were added to `GridSchema` for this adapter.

The useful future additions are narrowly scoped:

1. **An explicit document field name relative to its parent**, only if consumers later need naming rules beyond the agreed `alias_field` and `identity` contract. The current mapper requires no such addition; `elasticSearch.field` already supports individual overrides.
2. **An element type for generic `array` values**, if scalar arrays must be described independently of any renderer. This does not affect `collection`, which already means structured objects. Today dynamic mapping works without this addition; an optional `mapping.type` can make the mapping explicit later.
3. **A separate machine-readable date representation**, only if document serialization deliberately differs from the Grid output. Current `type.format`, `type.locale`, and `type.timezone` already describe the Grid output, so no new standard fields are needed for the mapper's current date support. A separate representation would also require document preparation to produce it consistently.

`nullable`, `required`, and collection cardinality may be useful for validation but are not required for basic ES field mapping. Exact numeric storage, analyzers, normalizers, multi-fields, and null substitution can remain in `renderer_parameters.elasticSearch.mapping`. Do not export Doctrine details or infer numeric storage from display precision merely to support this converter.

## Versioning rule for later changes

Adding an optional field is backward-compatible for version 1 readers that ignore unknown fields. Renaming a field, changing its JSON type, changing the meaning of an existing value, or normalizing values such as `cSSClass` to `cssClass` requires a new major schema version or an explicit compatibility mode.
