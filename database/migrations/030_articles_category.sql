-- LS-55: an optional category per article (the eight hardware categories, or empty for general), used by the blog sidebar. Additive: existing articles stay empty (general).
ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS category VARCHAR(20) NULL AFTER excerpt;
