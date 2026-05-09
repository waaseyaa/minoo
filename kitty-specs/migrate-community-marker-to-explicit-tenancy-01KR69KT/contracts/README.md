# Contracts

This mission introduces no new external contracts.

The relevant contract is the framework's `EntityType` constructor
signature, which is already fixed in alpha.173:

```php
new EntityType(
    // ...
    tenancy: ['scope' => 'community']
)
```

That contract lives at `vendor/waaseyaa/entity/src/EntityType.php` and is
not authored by this mission. See `research.md` Decision 1 for the full
verification trail.
