---
paths:
  - "app/**"
---

# Architecture

Backend code is organised as modules under `app/Modules/<Module>/`:

```
app/Modules/<Module>/
├── Application/
│   ├── Actions/        UpsertContact.php
│   ├── Assemblers/     ContactDataFromRequest.php, ContactDataFromCli.php
│   ├── DTOs/           UpsertContactData.php
│   └── Validation/     ContactValidator.php
├── Domain/
│   └── Models/         Contact.php, ContactEmail.php, ContactPhone.php
└── Presentation/
    ├── Http/Controllers/   ContactController.php
    └── Console/Commands/   UpsertContactCommand.php
```

## Layers

- Dependencies point inward: Presentation -> Application -> Domain. Domain never uses Application or Presentation. Application never uses Presentation.
- Modules talk to each other only through the other module's Application layer. Never reach into another module's Domain or Presentation.

## Entry points

- Controllers, commands and jobs stay thin: validator -> assembler -> DTO -> Action -> format output. No business logic in Presentation.
- Use one validator per use case, shared by HTTP and CLI, so both enforce identical rules.
- An assembler builds a DTO from one input source and is named for it: `ContactDataFromRequest`, `ContactDataFromCli`.

## Actions and DTOs

- An Action does one thing, takes typed DTOs and returns a typed result.
- An Action that writes to more than one table wraps the work in one `DB::transaction`.
- DTOs are `final readonly` classes with typed properties. Never pass raw arrays into an Action.

## Models and providers

- Eloquent models live in `Domain/Models`. This is a pragmatic concession, not strict DDD.
- Each module has its own service provider that registers its routes and commands.
- List that provider in `bootstrap/providers.php`. That line is the only central file a new module touches. Do not write a custom module loader.

The `tests/Arch` tests enforce the layer rules.
