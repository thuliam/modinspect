# ADR-003: Deterministic Price Engine

Status: Accepted

Authoritative market references are calculated by deterministic PHP code from accepted observations.

Consequences:

- LLMs can extract or classify candidate evidence.
- LLMs cannot publish price ranges, medians, or confidence as market truth.
- Asking, sold, trade-in, and new-price cohorts remain separate.
