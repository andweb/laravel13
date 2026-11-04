CREATE TABLE IF NOT EXISTS app_logs
(
    ts DateTime64(3) DEFAULT now64(3),
    level LowCardinality(String),
    message String,
    order_id Nullable(String)
)
ENGINE = MergeTree
ORDER BY (ts, level);
