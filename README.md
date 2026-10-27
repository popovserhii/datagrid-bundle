# PopovDatagridBundle
The **PopovDatagridBundle** allows you to integrate [ZfcDatagrid](https://github.com/zfc-datagrid/zfc-datagrid) into your Symfony project

## Remote listings

`Popov\DatagridBundle\DataSource\Remote` implements
`ZfcDatagrid\DataSource\DataSourceInterface` through `AbstractDataSource`.
It forwards the grid's final conditions to an existing external listing API:

```php
$source = new \Popov\DatagridBundle\DataSource\Remote(
    $httpClient,
    'http://remote.internal.local',
    'project', // Resource requested at /api/v1/project.
    $serviceToken
);
$datagrid->setDataSource($source);
```

The endpoint is the base URL; Remote appends `/api/v1/{resource}`.
Requests use GET with the regular parameters
`filters`, `sortBy`, `sortOrder`, `groupBy`, `page` and `itemsPerPage`. There is no
private POST protocol, operation parameter, column schema or Elasticsearch DSL.
The external system chooses its own data source and executes the query.

The existing renderer and projection flow stays unchanged. After
`Json\Renderer::prepareFilters()` removes ignored rules, Datagrid supplies the
final `FilterGroup`, sort conditions and group conditions through the regular
`DataSourceInterface` methods. Remote serializes those conditions back to the
public jqGrid query format internally. `Filter::getDisplayColumnValue()` retains
the prepared public value while the filter's parsed values remain available to
the local Doctrine and Laminas implementations.

For example, the outgoing `filters` JSON can be:

```json
{"groupOp":"AND","rules":[{"field":"project.type","op":"eq","data":"0"},{"field":"branch.id","op":"eq","data":"834"},{"field":"project.id","op":"in","data":"231,1407,90783"}]}
```

AND/OR groups and all standard operators retain the same public jqGrid format
accepted by the JSON renderer. Field names retain their grid aliases
(`project.title`, `branch.id`), underscores in field names are preserved, and
dates, timezones, localized numbers, lists and ranges reach the remote renderer
in their original public form. Sorting and grouping come from the existing
`prepareSortConditions()` and `prepareGroupConditions()` flow, including ignored
groups. An optional public field override can be declared with
`$column->setRendererParameter('field', 'some.path', 'remote')`.

The response is a standard listing object with `rows` and a `records` count,
optionally wrapped in `data` or `data.project`. Remote returns the `rows` array
unchanged: it does not rename keys, read nested fields, select columns or convert
types. The endpoint must return the same rows as a local DataSource before
`PrepareData`: column unique IDs such as `project_id` and `status_id`, with
unformatted source values. Formatting and JSON rendering then follow the same
path for every data source. Nested objects, additional fields, nulls, zeroes and
string values received from the endpoint are preserved by Remote.

There are two retrieval paths, matching the existing application structure.
`GridAbstractFactory` maps the `Remote` data source to `RemoteRetriever`, alongside
the existing Doctrine and Laminas mappings. It performs the unpaginated
`getListForResponse()` request without reading through the paginator adapter and
without adding `page` or `itemsPerPage`.
If the remote endpoint still applies a default limit, it follows the endpoint's
native pages until the exact `records` count is reached. The normal
`Datagrid::loadData()` path
uses the paginator adapter, including offsets that do not align with a page
boundary. Bounded adapter reads request the needed page directly, without a
preliminary request to determine the record count. They accept both an exact
`records` total and `records` equal to the number of returned rows, so a cursor
batch can finish with a short or empty response. Exact totals remain necessary
for the standard renderer's page metadata and for following server-imposed pages
during an unpaginated direct read. Endpoints that return all rows even when
pagination is requested are also supported. HTTP failures, invalid responses
and incorrect page numbers propagate as errors. Each `execute()` creates a fresh
adapter and clears the direct-result cache.

In `tb-web`, the `project_grid.elasticsearch_data_source` service configures the
base endpoint URL and resource. `PROJECT_GRID_ELASTICSEARCH_TOKEN` supplies the
optional bearer token.
`ProjectGridableService::USE_ELASTICSEARCH` selects the backend manually.
`getDataSource()` returns the interface for both backends; the ordinary grid
execution supplies the final conditions to it. `RemoteRetriever` mirrors the
existing Doctrine and Laminas retrievers for the unpaginated path, while the
DataSource still exposes an adapter for standard pagination. No extra controller
or module is required on either side specifically for Remote.
