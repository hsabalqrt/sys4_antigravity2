---
name: filament-development
description: Develops administrative interfaces using Filament v3. Activates when creating or modifying Filament resources, pages, widgets, clusters, or relation managers; working with form() and table() methods; adding actions, bulk actions, or filters; configuring navigation; using the search-docs tool for Filament; or when the user mentions Filament, resource, dashboard, or admin panel.
---


# Filament Development



## When to use this skill

Use this skill when you are working on anything related to Filament v3, including Resources, Widgets, Pages, and custom components.


## Core Principles

- **The Filament Way**: Use `php artisan make:filament-resource` and similar commands.
- **Fluent Interface**: Use method chaining for forms and tables.
- **Search Docs First**: Use the `search-docs` tool with `filament/filament` for any component options.


## Resources

Create resources using:
```shell
php artisan make:filament-resource {Name} --generate
```


## Forms

Define forms in the `form()` method:
```php
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('name')->required(),
        Select::make('status')->options([...]),
    ]);
}
```


## Tables

Define tables in the `table()` method:
```php
public static function table(Table $table): Table
{
    return $table->columns([
        TextColumn::make('name')->searchable(),
        IconColumn::make('is_active')->boolean(),
    ]);
}
```


## Widgets

Create widgets using:
```shell
php artisan make:filament-widget {Name}
```