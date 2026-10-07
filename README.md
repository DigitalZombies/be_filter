<div align="center">

# Backend Filter

**Filters for the TYPO3 record list, declared in TCA.**

[![TYPO3 14](https://img.shields.io/badge/TYPO3-14.3-orange.svg?logo=typo3&logoColor=white)](https://get.typo3.org/version/14)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777bb4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE.txt)

[Installation](#installation) · [Quick start](#quick-start) · [Defining filters](#defining-filters) · [Own filter types](#writing-your-own-filter-type) · [Limits](#limits)

</div>

`EXT:be_filter` puts a filter bar above the listing of a single table, remembers what each
editor filtered by, lets them save and share a filter, and replaces the column selector
with one that can be ordered.

<table>
  <tr>
    <td width="50%"><img src="Documentation/Images/filter-bar.jpg" alt="The filter bar above a listing"></td>
    <td width="50%"><img src="Documentation/Images/save-wizard.jpg" alt="The first step of the save wizard, naming the filter"></td>
  </tr>
  <tr>
    <td align="center"><sub>The filter bar above a listing</sub></td>
    <td align="center"><sub>Saving a filter with the wizard of the backend</sub></td>
  </tr>
</table>

The extension depends on `typo3/cms-backend` only and works in every module that renders
the record list, the Records module included.

## Why

The record list can sort and it can search, but it cannot filter: there is no way to say
*show me the cars of this manufacturer that are still available*. This adds that, without
a line of PHP for the usual cases — a filter is one line of TCA next to the column it
filters.

## Features

- **Filters declared in TCA** — select, date, number and checkbox filters are derived from
  the column, so most filters are a single line.
- **Dependent filters** — one filter narrows the options of another, so a combination that
  would return nothing cannot be picked.
- **Saved filters** — values *and* the displayed filters under a name, private, shared with
  backend groups, or public.
- **Column configuration** — which columns the list shows, and in which order.
- **Options fetched on demand** — a foreign table of any size costs the same page.
- **Extensible** — a filter type of your own is a service and a web component; a built in
  type can be replaced the same way.

## Requirements

| Requirement | Version |
| --- | --- |
| TYPO3 | 14.3 and newer |
| PHP | 8.2 and newer |
| Dependencies | `typo3/cms-backend` only |

## Installation

```bash
composer require digitalzombies/be_filter
vendor/bin/typo3 extension:setup
```

## Quick start

Declare the filters of a table next to `ctrl` and `columns`:

```php
$GLOBALS['TCA']['tx_shop_order']['filters'] = [
    'status'   => ['default' => true],
    'customer' => ['default' => true, 'multiple' => true],
    'project'  => ['multiple' => true, 'dependsOn' => 'customer'],
    'paid'     => ['render' => 'checkbox'],
    'crdate'   => ['type' => 'date'],
];
```

That is the whole setup: the bar appears above the listing of that table.

## Defining filters

Filters are declared per table, next to `ctrl` and `columns`:

```php
$GLOBALS['TCA']['tx_shop_order']['filters'] = [
    'status'   => ['default' => true],
    'customer' => ['default' => true, 'multiple' => true],
    'project'  => ['multiple' => true, 'dependsOn' => 'customer'],
    'paid'     => ['render' => 'checkbox'],
    'crdate'   => ['type' => 'date'],
];
```

| Key | Meaning |
| --- | --- |
| `default` | The filter is displayed until the user arranges the bar themselves. |
| `multiple` | Several values can be selected. Select filters only. |
| `render` | `checkbox` displays a checkbox instead of the yes / no dropdown. Checkbox filters only. |
| `dependsOn` | Filter name, or a list of them, narrowing the options of this filter. |
| `type` | Only needed for fields without a TCA column, `crdate` for example. |
| `label` | Overrides the label of the column. |
| `labels` | Captions of the two ends of a range, `from` and `to`. Date and number filters only. |
| `presets` | The quick ranges of a date filter, see below. |
| `optionLimit` | How many options the dropdown offers at once. Default: the setting of the installation, else 50. |
| `min`, `max` | Bounds of a number filter. Default: `range.lower` / `range.upper` of the column. |
| `step` | Step of a number filter, `steps` works as well. Default: `slider.step`, else 1, else the precision of a decimal. |
| `decimals` | Decimal places of a number filter. Default: `precision` of a decimal column, else 0. |

### Filter types

The type follows the TCA column, an explicit `type` is only read for fields that have
no column:

| TCA column | Filter | Behaviour |
| --- | --- | --- |
| `select`, `category`, `group`, `radio` | select | Dropdown with a search field. Values come from the items of the column, from its foreign table, or from both. Single values, comma separated lists and MM relations are all supported. |
| `datetime` | date | From / to with the presets *1 week*, *1 month*, *last month*, *this month*, *last year*, *this year*. Presets are stored by name, so a saved filter keeps moving with the calendar. |
| `check` | checkbox | A *yes / no* dropdown by default, a checkbox with `'render' => 'checkbox'`. Checkboxes displaying the unchecked state as the enabled one, `hidden`, are handled. |
| `number` | number | From / to, for whole numbers and decimals alike. Bounds and step come from the column and can be overridden. `input` columns with a numeric `eval` are treated the same. |

Everything else is ignored, text fields above all: searching them is what the search box
of the record list is for.

The items of a column follow the page, the same way the record list follows it: what
`TCEFORM` keeps, removes or renames applies to the filter as well.

```
TCEFORM.tx_my_table.status.removeItems = draft
TCEFORM.tx_my_table.status.altLabels.done = Finished
```

A `group` field is only offered when it allows a single table, and a `check` field only
when it has a single checkbox.

### Date presets

A date filter offers quick ranges. Without configuration these are *1 week*, *1 month*,
*last month*, *this month*, *last year* and *this year*. A filter may replace them:

```php
'crdate' => [
    'type' => 'date',
    'presets' => [
        'thisMonth' => true,                                          // a default, unchanged
        'week'      => ['label' => 'LLL:EXT:my_ext/…:last7days'],      // a default, own label
        'quarter'   => ['label' => 'Quarter', 'from' => '-3 months'],  // a new one
        'overdue'   => ['label' => 'Overdue', 'to' => 'yesterday'],    // open at the start
    ],
],
```

Listing `presets` replaces the defaults: the keys given are offered, in that order. `true`
takes a default as it is, `false` skips an entry, an array defines or overrides one. The
defaults themselves are replaced the same way for every date filter of the installation:

```php
// ext_localconf.php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter']['datePresets']['week'] = [
    'label' => 'LLL:EXT:my_ext/…:last7days',
    'from' => '-7 days',
];
```

A preset is a label and a range. `from` and `to` are **relative date expressions of PHP**,
the syntax of `strtotime()`, evaluated against the current day whenever the filter runs —
that is why a saved filter using *this month* keeps moving with the calendar. `from` opens
the range at the start of that day, `to` closes it at the end of that day. Leaving one out
makes the range open on that side, and `'value' => '-7 days'` is short for a range reaching
until now.

| Expression | Resolves to |
| --- | --- |
| `-7 days` | seven days back |
| `-1 month` | the same day last month |
| `first day of last month` | the 1st of the previous month |
| `last day of this month` | the end of the current month |
| `monday this week` | the start of the current week |
| `first day of January this year` | the 1st of January |
| `today`, `now`, `yesterday` | the respective day |

An expression that cannot be parsed is ignored, it never takes the listing down.

### Number ranges

```php
'price'   => ['default' => true, 'step' => 500],
'mileage' => ['max' => 300000, 'step' => 1000],
'weight'  => ['decimals' => 2],                   // a decimal column, step becomes 0.01
'price'   => ['render' => 'slider', 'max' => 200000],    // two handles, from and to
'mileage' => ['render' => 'slider', 'mode' => 'max'],   // one handle, up to the value
'seats'   => ['render' => 'slider', 'mode' => 'exact'], // one handle, the value itself
```

A slider needs both ends to slide along: where the column names only one of them, the
filter has to name the other, otherwise the two fields are rendered instead.

Either end may be left empty: a range open on one side filters just as well. What the
column already declares is reused, so `min`, `max` and `step` are only needed to narrow
or refine it.

### Captions of a range

A from / to filter names its two ends *From* and *To*. Both can be replaced, for every
date and number filter of the installation, for one table, or for one filter:

```php
// ext_localconf.php, everywhere
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter']['labels'] = [
    'from' => 'LLL:EXT:my_ext/…:since',
    'to'   => 'LLL:EXT:my_ext/…:until',
];

// one table
$GLOBALS['TCA']['tx_my_table']['ctrl']['be_filter']['labels']['from'] = 'Registered from';

// one filter
'first_registration' => ['labels' => ['from' => 'Registered from', 'to' => 'Registered until']],
```

The narrower level wins per end: a table replacing `from` keeps what the installation says
about `to`. An empty caption is not a caption — it falls through to the level below, and an
end nobody configures keeps the translation of the extension. `LLL:` references are
resolved once, when the filters of the table are built.

A caption may be longer than *From*: every end has a row of its own, and a slider carries
its caption above the track, so the track keeps its length whatever the caption says.

### Who may filter

Filters are a feature of the backend user, so they are switched off with **user TSconfig**.
It is inherited from the backend groups of a user, which makes a group the place to say
who filters and who does not:

```
# nobody in this group filters anything
mod.be_filter.disabled = 1

# or only this table is off limits
mod.be_filter.tables.tx_my_table.disabled = 1
```

A user who may not filter a table gets no filter bar, and the endpoints behind it answer
nothing: the bar is not the only way in, so the same check guards every route.

This is not the same as the permissions of the table. A user who may not read a table has
no filter bar either, because the record list does not show them the table in the first
place.

### Auto refresh

By default a changed value is staged and applied with the button. Auto refresh reloads the
listing as soon as a value changes, and the *Apply* button disappears:

```php
// ext_localconf.php, the default for every table
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter']['autoRefresh'] = true;

// one table decides for itself
$GLOBALS['TCA']['tx_my_table']['ctrl']['be_filter']['autoRefresh'] = false;
```

Typing in a range does not send a request per keystroke: the reload waits until the typing
stops.

### How many options a dropdown offers

Options are never part of the page. They are fetched when a dropdown opens, and again
while the user types in its search field, so a foreign table of any size costs the same
page. A dropdown offers the first 50 of them and says what it is hiding — *50 of 1014
shown, search to narrow them down* — the search itself runs on the server, across every
label field of the table.

```php
'manufacturer' => ['optionLimit' => 100],                         // this filter
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter']['optionLimit'] = 20;   // everywhere
$GLOBALS['TCA']['tx_my_table']['ctrl']['be_filter']['optionLimit'] = 200;   // one table
```

Values that are selected stay visible whatever the limit is: their labels travel with the
page, so a chosen record is never hidden by the cut off.

### Dependent filters

`dependsOn` narrows the options of a filter to the values that actually occur in records
matching its parents, so a dependent filter never offers an option that would lead to an
empty list. It costs one query: the options are read from the filtered table joined to the
foreign table, so nothing depends on how many records that table holds. Fields that cannot
be joined portably — a comma separated list, a relation to the table itself — fall back to
collecting the values that occur and matching them. Parents without a value narrow nothing. Changing a parent clears the value of
its dependent filters.

## What the user can do

- **Filter bar** — every displayed filter with its current value. Changes are staged,
  *Apply* stores them for the user and reloads the list, *Reset* clears them.
- **Saved filters** — the current values *and* the filters displayed in the bar, stored
  under a name of their own. A saved filter is private, shared with backend groups, or
  public; only its owner may change or delete it. The bar carries a button for each half:
  - *Save* opens a wizard built on the wizard of the backend itself
    (`typo3-backend-wizard`), so it behaves like the page creation wizard: the name, then
    who the filter is shared with, then a summary. Sharing with backend groups asks for
    the groups and stays incomplete until one is chosen.
  - A saved filter carries the records it was saved with. Opened by somebody who may not
    see one of them — a different scope, a permission they do not have, or a record that
    has since been deleted — the values that no longer resolve are left out instead of
    filtering the listing down to nothing, and a notice names the filters it happened to.
    The listing is scoped on its own, so leaving a value out never shows more than the
    user may see. A filter the table no longer has, or the user is not allowed to see, is
    named in the same notice.
  - Only the records a user may see leave the server: a saved filter shared with everybody
    hands each of them the values they can resolve, not the ones they cannot.
  - *Open* is a dialog, not a wizard: the saved filters as cards, searchable by name,
    creator (your own show as *Me*), date and who they are shared with. Select one and
    apply, or apply right away with a double click.
- **Filters** — which filters the bar shows, and in which order.
- **Columns** — which columns the list shows, and in which order. It replaces the column
  selector of the record list, which offers a selection but no order.

Both open the same popup: two panels, *Available* on the left and *Displayed* on the right.
Entries are dragged from one panel to the other, and the order of the right panel becomes
the order in the list. Double clicking an entry moves it over as well. Entries that cannot
be switched off, the title column of a table for example, stay at the top of the right panel.

The active filter values and the displayed filters are kept in the `uc` of the backend
user, in the module data section: the columns where the record list reads them,
`list/displayFields`, the rest under `be_filter`.

Saved filters are shared, so they live in `tx_befilter_preset`: owner, table, title, the
values, the displayed filters, and the sharing (`private`, `groups` with a list of backend
groups, or `public`).

## Writing your own filter type

A filter type is a service. It decides what a filter accepts, how it constrains the query
and which control renders it. Nothing has to be registered by hand: the container collects
every service implementing `Filter\Type\FilterTypeInterface`.

The tag that collects them does not cross extensions: `_instanceof` applies to the services
of the `Services.yaml` it stands in, so an extension adding a type declares it in its own:

```yaml
services:
  _instanceof:
    DigitalZombies\BeFilter\Filter\Type\FilterTypeInterface:
      tags: ['be_filter.filter_type']

  MyVendor\MyExt\:
    resource: '../Classes/*'
```

EXT:example_fleet ships a worked example of everything below: a colour swatch filter in
`Classes/Filter/Type/ColourSwatchFilterType.php`, its control in
`Resources/Public/JavaScript/colour-filter.js`.

`Filter\Type\AbstractFilterType` answers everything optional, so four methods are left:

```php
final class RatingFilterType extends AbstractFilterType
{
    public function getIdentifier(): string
    {
        return 'rating';
    }

    // Which columns this type takes over. Answer a higher getPriority() to win
    // against a built in type for the same column.
    public function matches(array $config, array $definition): bool
    {
        return ($config['type'] ?? '') === 'number' && ($definition['render'] ?? '') === 'stars';
    }

    // What the filter accepts from the client, NULL when it constrains nothing.
    public function normalizeValue(mixed $value, Filter $filter): mixed
    {
        $stars = (int)$value;

        return $stars >= 1 && $stars <= 5 ? $stars : null;
    }

    public function buildConstraint(QueryBuilder $queryBuilder, Filter $filter, mixed $value): string
    {
        return $queryBuilder->expr()->gte(
            $filter->table . '.' . $filter->name,
            $queryBuilder->createNamedParameter((int)$value, Connection::PARAM_INT)
        );
    }

    public function getJavaScriptModule(): ?string
    {
        return '@my-vendor/my-ext/rating-filter.js';
    }
}
```

The optional ones: `buildTypeOptions()` keeps whatever the type needs on the filter
(`Filter::$typeOptions`), `describe()` hands that to the control, `providesOptions()` says
whether the filter offers a list of values through the options endpoint, and
`getPriority()` orders the types.

The module named above brings the control. `AbstractFilterControl` renders the dropdown
with the label and the current value, so a control fills in `renderControl()`. A control
that is not a dropdown overrides `renderShell()` as well, which is what the checkbox
filter does:

```js
import { html } from "lit";
import { AbstractFilterControl, registerFilterType } from "@digitalzombies/be_filter/filter-types.js";

class RatingFilter extends AbstractFilterControl {
  renderControl() {
    return html`
      ${[1, 2, 3, 4, 5].map((stars) => html`
        <button type="button" class="btn btn-link" @click=${() => this.emit(stars)}>${stars}</button>`)}`;
  }

  summary() {
    return this.value ? `${this.value}+` : "";
  }
}

customElements.define("my-rating-filter", RatingFilter);
registerFilterType("rating", "my-rating-filter");
```

The specifier has to resolve in the backend, so the extension declares its prefix:

```php
// Configuration/JavaScriptModules.php
return [
    'dependencies' => ['backend', 'core', 'be_filter'],
    'imports' => ['@my-vendor/my-ext/' => 'EXT:my_ext/Resources/Public/JavaScript/'],
];
```

The backend loads every module as its own async script, so a control registers whenever
its module happens to run — before or after the bar was rendered. The bar listens for
registrations and renders again, so the order the modules arrive in does not matter.

Build a control out of what the backend already ships rather than styling it yourself: a
checkbox in a `form-check form-check-type-toggle` is the one the record list puts in front
of a row, `form-check-type-card` is the card of the wizards, and a control of the bar goes
into `be-filter-control`. A control may be rendered inside a modal, where the stylesheet
of this extension is not loaded at all, so a class of the core carries further than one of
your own.

`this.emit(value)` hands a value to the bar, `null` clears the filter. `summary()` is what
stands next to the label, `opened()` is called when the menu appears — for a control that
loads its values.

**The built in types are written exactly this way.** Each brings its own module, defines
its element and registers it, and its type names that module through
`getJavaScriptModule()`:

| Type | Type class | Control |
| --- | --- | --- |
| `select` | `Filter\Type\SelectFilterType` | `select-filter.js` |
| `date` | `Filter\Type\DateFilterType` | `date-filter.js` |
| `number` | `Filter\Type\NumberFilterType` | `number-filter.js` |
| `checkbox` | `Filter\Type\CheckboxFilterType` | `checkbox-filter.js` |

`filter-bar.js` is the bar alone: it knows no type by name, it asks the registry which
control renders a filter.

### Replacing a built in type

A type of your own that answers an identifier already taken, and a higher `getPriority()`,
takes it over. The registry keeps one type per identifier, so the replaced control is not
even loaded:

```php
final class MySelectFilterType extends AbstractFilterType
{
    public function getIdentifier(): string
    {
        return 'select';          // the name of the built in type
    }

    public function getPriority(): int
    {
        return 10;                // built in types answer 0
    }

    public function getJavaScriptModule(): ?string
    {
        return '@my-vendor/my-ext/select-filter.js';
    }
}
```

The control is replaced the same way: registering another tag under an identifier replaces
what was registered before, and the module of the winning type is the one that is loaded.

Values travel to the server as they are emitted and come back through
`normalizeValue()`, so a control never has to sanitise anything itself.

## Extension points

| Concern | Mechanism |
| --- | --- |
| Filter constraints and column order | `ModifyDatabaseQueryForRecordListingEvent` |
| Filter bar | `RenderAdditionalContentToRecordListEvent` |
| Constraints on the option queries of the filters | `Event\ModifyFilterQueryEvent` |

`ModifyFilterQueryEvent` is dispatched for every query the filters run on their own:
looking up selectable options, and narrowing dependent filters. Listen to it when an
application scopes records beyond what TCA describes:

```php
#[AsEventListener('my/scope-filter-options')]
public function __invoke(ModifyFilterQueryEvent $event): void
{
    $this->addMyConstraint($event->getQueryBuilder(), $event->getTable());
}
```

## Limits

- The filter bar belongs to the single table view. In the multi table listing the record
  list keeps its own column selector.
- `foreign_table_where` and `itemsProcFunc` of a column are not evaluated. Options are the
  records of the foreign table and the static items of the column. Of `TCEFORM`, the
  `keepItems`, `removeItems` and `altLabels` of a column are followed; `addItems` is not,
  an item no record carries would filter to nothing.
- Not covered so far: checkboxes with several items (a bitmask) and free text. Text is
  left to the search box of the record list on purpose.

## Tests

Two suites, both without a TYPO3 instance: the PHP one covers the classes that decide what
a filter accepts and how a range is resolved, the JavaScript one covers the logic of the
components.

```bash
ddev exec vendor/bin/phpunit -c packages/be_filter/phpunit.xml.dist
ddev exec bash -c 'node --import ./packages/be_filter/Tests/JavaScript/register.mjs --test packages/be_filter/Tests/JavaScript/*.test.mjs'
```

The test files are named one by one on purpose: handed a directory, the runner imports it
as a module and stops with `ERR_UNSUPPORTED_DIR_IMPORT`.

The JavaScript modules import `lit`, `@typo3/...` and `~labels/...`, which only resolve
inside the backend. `Tests/JavaScript/loader.mjs` maps those specifiers to small stubs in
`Tests/JavaScript/stubs/`, so the modules can be imported in plain Node. Node 18 or newer
is needed for the test runner; the DDEV container has it, a host does not have to.

<details>
<summary>What each suite covers</summary>

| Suite | Covers |
| --- | --- |
| `Tests/Unit/Filter/DatePresetTest` | Quick ranges: whole days, open ends, calendar expressions, an expression an extension got wrong |
| `Tests/Unit/Filter/DatePresetRegistryTest` | Defaults, replacing them per filter and per installation, the `value` short form |
| `Tests/Unit/Filter/FilterSettingsTest` | Auto refresh and option limit: default, installation, table |
| `Tests/Unit/Filter/FilterTest` | How the values of a field are stored: relations, MM, items |
| `Tests/Unit/Filter/FilterTypeRegistryTest` | Finding a type by name, by column, and by priority |
| `Tests/Unit/Filter/Type/SelectFilterTypeTest` | Which columns it takes, single and multiple values |
| `Tests/Unit/Filter/Type/CheckboxFilterTypeTest` | Single checkboxes, dropdown versus switch |
| `Tests/Unit/Filter/Type/DateFilterTypeTest` | Quick ranges, ISO ranges, what it rejects |
| `Tests/Unit/Filter/Type/NumberFilterTypeTest` | Bounds, step, decimals, slider and its modes |
| `Tests/Unit/Filter/Type/AbstractFilterTypeTest` | What a type gets for free, and the captions of a range |
| `Tests/Unit/Event/ModifyFilterQueryEventTest` | What a listener is handed |
| `Tests/Unit/Event/ModifyPresetQueryEventTest` | How an application scopes saved filters |
| `Tests/Unit/Filter/ReachableValuesTest` | Values the user cannot reach: dropped, the filter named, a name with no filter behind it |
| `Tests/Unit/Query/ConstraintBuilderTest` | Which type is asked for a constraint, and which values reach the query |
| `Tests/Unit/State/FilterStateRepositoryTest` | What is kept per user: the values and the bar, and a filter that is gone |
| `Tests/JavaScript/support.test.mjs` | Label fallback, dates, the audience of a saved filter |
| `Tests/JavaScript/number-filter.test.mjs` | Number ranges, both ends entered in one go |
| `Tests/JavaScript/date-filter.test.mjs` | Date ranges and quick ranges |
| `Tests/JavaScript/filter-bar.test.mjs` | Dependent filters, dirty state, auto refresh, missing labels |
| `Tests/JavaScript/preset-open.test.mjs` | Searching saved filters and picking one |
| `Tests/JavaScript/filter-types.test.mjs` | The control registry, the contract of `AbstractFilterControl`, replacing a built in control |
| `Tests/JavaScript/select-filter.test.mjs` | Choosing values, the order of the list, the summary |
| `Tests/JavaScript/checkbox-filter.test.mjs` | The shell it brings itself, and the states it emits |
| `Tests/JavaScript/slider-filter.test.mjs` | Slider modes, and the fallback when a bound is missing |
| `Tests/JavaScript/adjusted-notice.test.mjs` | The notice about dropped values: carried over the reload, said once |

</details>

The constraints the types build are covered: `Tests/Unit/QueryBuilderTrait` hands them a
query builder that writes down what it is asked for, so a constraint can be asserted as a
string without a database. `ConstraintBuilder` is tested the same way. What reads from the
database — `OptionProvider`, `PresetRepository` — needs a real one, so it belongs in a
functional suite.

## Coding standards

The code follows the coding standards of the TYPO3 core, enforced by
`typo3/coding-standards`:

```bash
ddev exec vendor/bin/php-cs-fixer fix --dry-run --diff   # what would change
ddev exec vendor/bin/php-cs-fixer fix                    # change it
```

The configuration is `.php-cs-fixer.dist.php` in the root of the project.

## Licence

GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt).

TYPO3 is a registered trademark of Kasper Skårhøj.

## Author

Digital Zombies GmbH — <https://www.digital-zombies.com>
