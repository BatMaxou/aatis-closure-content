# Aatis Closure Content

## Installation

```bash
composer require aatis/closure-content
```

## Usage

Retrieve the content of a callable as string

```php
ClosureContent::of([$this, 'method']);
ClosureContent::of($this->method(...));
ClosureContent::of(function () {
    // some stuff
});
ClosureContent::of(fn() => /* some stuff */);
ClosureContent::of(new InvokableClass());
```
