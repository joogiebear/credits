# Credits - A Credit/Points System for MyBB 1.8

A fully-featured virtual currency plugin for MyBB forums. Users earn credits through forum activity, spend them in a shop on cosmetic items and perks, gift them to other users, and more.

## Requirements

- MyBB 1.8.x
- PHP 7.0+
- [PluginLibrary](https://community.mybb.com/mods.php?action=view&pid=573)

## Installation

1. Download the latest release
2. Upload the contents of the `Upload/` folder to your MyBB root directory
3. Go to **ACP > Configuration > Plugins**
4. Find **Credits** and click **Install & Activate**

The plugin will automatically:
- Create all required database tables
- Add default shop categories and sample items
- Generate clean URL entry points (`credits.php` and `inventory.php`)
- Inject the stylesheet into your theme
- Create a scheduled task for expiry processing

## Features

### Credit Earning
Users earn credits automatically through forum activity:
- **New posts** and **new threads** (configurable amounts)
- **Receiving reputation** points
- **Daily login bonus** (once per 24 hours)
- **Referral rewards** when referred users reach a post threshold
- **Achievement bonuses** for hitting milestones

Active **boosters** multiply credits earned from organic activity (posts, threads, reputation, login bonuses). Boosters do not apply to purchases, gifts, or admin adjustments.

### Shop
A full item shop where users spend credits on cosmetics and perks:

| Item Type | Description |
|-----------|-------------|
| Custom Title | Custom text displayed under username |
| Username Color | Hex color applied to username in posts |
| Icon | Badge/image displayed next to username |
| Award | Collectible badge shown on profile |
| Booster | Temporary credit earning multiplier (2x-10x) |
| Post Background | Custom background for user's posts (color, gradient, or image) |
| Username Effect | Animated username effect (rainbow, glow, sparkle, shadow, bold, gradient) |
| Usergroup Subscription | Temporary or permanent usergroup membership |
| Ad Space | Purchasable ad placement on the forum |

Items are organized into admin-created categories with sidebar navigation. Stock management, dual pricing (credits and/or real money), and display ordering are all configurable per item.

### Inventory
Users manage their purchased items from the inventory page:
- View all owned items grouped by type
- Toggle items active/inactive
- Edit item values (titles, colors, etc.)
- See item status: Active, Inactive, or Expired

### Gifting
Users can send credits or purchased items to other users with an optional message. A configurable minimum post count is required before sending gifts. Recipients are notified via PM.

### Achievements
Milestone-based achievements that reward users for forum activity:
- **Post count**, **thread count**, **reputation**, **account age**, **purchase count**, **credit balance**
- Rewards: bonus credits and/or temporary boosters
- Progress tracking visible to users
- Earned achievements displayed on user profiles

### Lottery
Admin-created raffles where users buy tickets with credits:
- Configurable ticket price and max tickets per user
- Automatic drawing at scheduled time via task
- Winner receives a configurable percentage of the total pot
- PM notification to the winner

### Referral Program
Users share a unique referral code. When a referred user reaches the configured post threshold, the referrer receives a credit reward and an optional temporary booster.

### Ad Space
Users can purchase ad placements (header or thread header) with credits. Ads support text or image content with click-through URLs. Optional admin approval before ads go live, with view/click tracking and configurable exempt usergroups.

### Leaderboard
Ranks users by credit balance with configurable entry count.

### Credit Log
Full transaction history for each user showing action type, amount, resulting balance, and timestamp.

### Payment Gateways
Accept real-money payments for credit packs and shop items:
- **Coinbase Commerce** - Cryptocurrency payments (Bitcoin, Ethereum, etc.)
- **Lemon Squeezy** - Card and PayPal payments

Both gateways use cryptographic webhook signature verification.

## Admin Control Panel

Access via **ACP > Users & Groups > Credits**:

| Tab | Description |
|-----|-------------|
| Users | Browse users and credit balances |
| Adjust | Add, subtract, or set credits for a user |
| Credit Log | View all transactions with filtering |
| Categories | Create and manage shop categories |
| Shop Items | Create and manage shop items |
| Credit Packs | Create real-money credit packages |
| Payments | View payment gateway transactions |
| Gifts | View gift history |
| Ad Space | Manage and approve user-purchased ads |
| Achievements | Create and manage achievements |
| Lottery | Create and manage lotteries |
| Referrals | View referral relationships |

## Settings

All settings are found in **ACP > Configuration > Settings > Credits Settings**:

- **Enable/disable** the plugin, shop, gifting, achievements, lottery, referrals, and ads
- **Credit amounts** for posts, threads, reputation, and daily login
- **Display settings** for leaderboard count and items per page
- **Gifting** minimum post requirement
- **Payment gateway** API keys and webhook secrets (Coinbase, Lemon Squeezy)
- **Ad space** approval requirement and exempt usergroups
- **Referral** reward amount, post threshold, and optional booster

## File Structure

```
inc/plugins/credits.php          # Main plugin file (install, activate, hooks)
inc/plugins/credits/
    admin.php                    # ACP module
    api.php                      # API for third-party plugins
    core.php                     # Core functions (add/subtract/log credits, URL helper)
    gifting.php                  # Gifting system
    hooks.php                    # Hook implementations (postbit, profile, earning, pages)
    inventory.php                # Inventory management
    payments.php                 # Payment gateway integrations
    shop.php                     # Shop system
inc/languages/english/
    credits.lang.php             # Frontend language strings
    admin/credits.lang.php       # Admin language strings
inc/tasks/credits.php            # Scheduled task (expiry processing, lottery draws)
jscripts/credits.js              # Frontend JavaScript (AJAX, inventory, shop UI)
credits.php                      # Clean URL entry point (auto-generated)
inventory.php                    # Clean URL entry point (auto-generated)
credits_webhook_coinbase.php     # Coinbase Commerce webhook endpoint
credits_webhook_lemonsqueezy.php # Lemon Squeezy webhook endpoint
```

## API for Third-Party Plugins

```php
// Check if Credits is active
credits_api_is_active();

// Register a custom action type for the credit log
credits_api_register_action('my_action', 'My Custom Action');

// Get the configured currency name
credits_api_get_currency_name();
```

## Uninstallation

Deactivating the plugin removes template injections and disables the stylesheet. Uninstalling removes all database tables, user columns, templates, settings, the scheduled task, and generated entry point files.

## License

MIT
