-- Run once on an existing database to rename tables without losing data.
RENAME TABLE
  users TO words_users,
  lists TO words_lists,
  words TO words_words;
