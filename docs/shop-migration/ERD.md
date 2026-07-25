# Shop module — Entity Relationship Diagram

Post-migration schema (all tables `shop_*`). The seller entity keeps the name
**seller** (`shop_sellers`) — CLAUDE.md reserves *Merchant* for the financial
core (`app/Domain/Merchant`). Buyers are core `users`.

```mermaid
erDiagram
    users ||--o{ shop_sellers : "is"
    users ||--o{ shop_orders : "buys (buyer_user_id)"

    shop_sellers ||--o{ shop_seller_applications : applies
    shop_sellers ||--o{ shop_products : owns
    shop_sellers ||--o{ shop_sales_pages : owns
    shop_sellers ||--o{ shop_funnels : owns
    shop_sellers ||--o{ shop_coupons : issues
    shop_sellers ||--o{ shop_domains : verifies
    shop_sellers ||--o{ shop_saved_blocks : saves
    shop_sellers ||--o{ shop_orders : fulfils
    shop_sellers ||--o{ shop_analytics_events : tracks
    shop_sellers ||--o{ shop_daily_stats : "rolled up into"

    shop_products ||--o{ shop_product_variants : has
    shop_products ||--o{ shop_product_files : delivers
    shop_products ||--o{ shop_product_media : shows
    shop_products ||--o{ shop_order_items : "sold as"
    shop_products ||--o{ shop_reviews : "reviewed in"
    shop_products ||--o{ shop_licenses : "licensed as"

    shop_sales_pages ||--o{ shop_page_revisions : versions
    shop_sales_pages ||--o{ shop_orders : "converts via"
    shop_sales_pages ||--o{ shop_domains : "served on"

    shop_funnels ||--o{ shop_funnel_steps : contains
    shop_products ||--o{ shop_funnel_steps : "offered in"

    shop_orders ||--o{ shop_order_items : "line items"
    shop_orders ||--o{ shop_order_events : "audit trail"
    shop_orders ||--o{ shop_messages : "buyer↔seller thread"
    shop_orders ||--o{ shop_shipments : ships
    shop_orders ||--o{ shop_refund_requests : disputes
    shop_orders ||--o{ shop_licenses : grants
    shop_coupons ||--o{ shop_orders : discounts

    shop_order_items ||--o{ shop_downloads : "delivered via"
    shop_product_files ||--o{ shop_downloads : "file for"
    shop_downloads ||--o{ shop_download_events : "access log"

    shop_messages ||--o{ shop_message_attachments : attaches

    shop_sellers {
        uuid id PK
        uuid user_id FK
        string status
        string plan
        string logo_path
        int commission_bps
    }
    shop_products {
        uuid id PK
        uuid seller_id FK
        string type
        string status
        string slug
        bigint price_amount
    }
    shop_orders {
        uuid id PK
        string number
        uuid seller_id FK
        uuid buyer_user_id FK
        uuid sales_page_id FK
        uuid funnel_id FK
        string status
        bigint total_amount
        bigint commission_amount
        bigint seller_net_amount
    }
    shop_refund_requests {
        uuid id PK
        uuid order_id FK
        string status
        text reason
    }
```

### Table inventory (26)
`shop_sellers`, `shop_seller_applications`, `shop_products`, `shop_product_variants`,
`shop_product_files`, `shop_product_media`, `shop_sales_pages`, `shop_page_revisions`,
`shop_saved_blocks`, `shop_domains`, `shop_funnels`, `shop_funnel_steps`, `shop_orders`,
`shop_order_items`, `shop_order_events`, `shop_coupons`, `shop_downloads`,
`shop_download_events`, `shop_licenses`, `shop_reviews`, `shop_messages`,
`shop_message_attachments`, `shop_shipments`, `shop_refund_requests`,
`shop_analytics_events`, `shop_daily_stats`.

### Ledger touchpoint
Shop commission revenue posts to `LedgerAccountType::ShopCommissionIncome`
(backing value `shop:commission_income`). Money never bypasses the ledger; the
seller's net is held then released per the `shop_earnings_hold` flag.
