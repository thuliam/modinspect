# ADR-002: Product Master Vs Observation

Status: Accepted

Canonical product facts are stored separately from market observations.

Product Master changes slowly. Price, condition, warranty, location, source, evidence level, and timestamps belong to observations.

Consequences:

- Never overwrite product master fields from listing text.
- New aliases and variants can be suggested from market evidence, but require review.
