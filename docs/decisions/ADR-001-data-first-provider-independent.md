# ADR-001: Data-First Provider-Independent Collection

Status: Accepted

ModInspect treats providers as replaceable adapters. The application owns product, observation, evidence, validation, and pricing contracts.

Consequences:

- Core behavior must run with providers disabled.
- Mock/fixture providers are required for tests.
- Live providers require explicit enablement and budget/source controls.
