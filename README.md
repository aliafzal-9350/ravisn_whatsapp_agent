# 💬 ZeroMsg — Self-Hosted WhatsApp Cloud API Platform

> **Modern, self-hosted platform for WhatsApp Cloud API management: live customer inbox, bulk marketing campaigns, visual automation flows, official Meta message template sync, and developer REST APIs.**

---

## 🌟 Overview

**ZeroMsg** is an all-in-one open-source/self-hosted WhatsApp Business Solution Provider (BSP) dashboard and automated marketing engine built on top of **Laravel 13**, **Inertia.js v3**, **React 19**, and **Tailwind CSS v4**.

It connects directly with official **Meta WhatsApp Cloud API**, enabling businesses, agencies, and developers to manage live customer conversations, launch automated marketing campaigns, design interactive chatbot automation flows, and integrate WhatsApp messaging into custom software applications.

---

## 🔥 Key Features

### 📨 Live Customer Inbox
- **Real-Time Customer Messaging**: Interactive conversation view supporting text, media, documents, and template messages.
- **Message Delivery Statuses**: Real-time tracking of sent, delivered, read, and failed message statuses via Meta webhooks.
- **Multi-Account Support**: Switch seamlessly across multiple connected WhatsApp Business Accounts (WABAs).

### 🚀 Bulk WhatsApp Campaigns
- **Targeted Broadcasts**: Create and schedule mass broadcast campaigns to contact groups or custom subscriber lists.
- **CSV List Uploads**: Import contacts and recipient lists directly via CSV for fast campaign execution.
- **Campaign Controls**: Pause, resume, start, and monitor real-time delivery performance per recipient.

### ⚡ Visual Automation Flow Builder
- **Drag-and-Drop Canvas**: Built with `@xyflow/react` (React Flow) for creating interactive visual messaging flows.
- **Chatbot Rules & Triggers**: Configure automated trigger responses, conditional branching, and message actions.
- **Workflow Portability**: Export and import complete automation flows as JSON templates.

### 📜 Meta Message Templates Manager
- **Official Template Sync**: Submit, review, and sync WhatsApp Message Templates (Utility, Marketing, Authentication) directly with Meta API.
- **Interactive Component Builder**: Define header images/documents, body variables, footers, and call-to-action/quick-reply buttons.

### 👥 Audience & Contact Management
- **Contact Database**: Store customer phone numbers, metadata, custom attributes, and opt-in preferences.
- **Contact Groups**: Segment contacts into dynamic target groups for campaign broadcasting.
- **Import / Export**: Full CSV export and import support.

### 🛠️ Developer Platform & APIs
- **RESTful Messaging API (`/api/v1/messages/*`)**: Send text messages and WhatsApp template messages programmatically.
- **API Keys Management**: Generate, rotate, and scope bearer tokens for third-party system integrations.
- **Outgoing Webhooks**: Send event payloads (incoming messages, delivery updates) to your custom endpoints.

---

## 🛠️ Technology Stack

| Layer | Technology |
| :--- | :--- |
| **Backend Framework** | [Laravel 13](https://laravel.com/) (PHP 8.3+) |
| **Frontend Adapter** | [Inertia.js v3](https://inertiajs.com/) |
| **UI Library** | [React 19](https://react.dev/), [TypeScript](https://www.typescriptlang.org/) |
| **Styling & Components** | [Tailwind CSS v4](https://tailwindcss.com/), [Radix UI primitives](https://www.radix-ui.com/), Lucide Icons |
| **Flow Builder Canvas** | [@xyflow/react (React Flow)](https://reactflow.dev/) |
| **Authentication** | [Laravel Fortify](https://laravel.com/docs/fortify) (with Passkeys & WebAuthn support) |
| **Routing / Actions** | [Laravel Wayfinder](https://github.com/laravel/wayfinder) |
| **Testing** | [Pest v4](https://pestphp.com/) & PHPUnit v12 |
| **Build & Tooling** | Vite 8, Laravel Pint, ESLint 9, Prettier |

---

## 📋 System Requirements

- **PHP**: `^8.3` (with `pdo`, `mbstring`, `openssl`, `curl`, `ctype`, `json` extensions)
- **Node.js**: `^20.0` or `^22.0`
- **Composer**: `^2.7`
- **Database**: SQLite (default), MySQL 8.0+, or PostgreSQL 14+
- **Meta Developer Account**: Access to Meta WhatsApp Cloud API credentials.

---

## 🚀 Quick Start & Installation

### 1. Clone the Repository

```bash
git clone https://github.com/megoxv/zeromsg.git
cd zeromsg
```

### 2. Run Automatic Setup

ZeroMsg includes built-in setup scripts to install dependencies, generate application keys, configure the database, and build frontend assets.

```bash
composer run setup
```

*Or step-by-step:*

```bash
# Copy environment configuration
cp .env.example .env

# Install PHP dependencies
composer install

# Generate application key
php artisan key:generate

# Prepare SQLite database (or configure MySQL/PostgreSQL in .env)
touch database/database.sqlite
php artisan migrate --force

# Install Node modules & build production assets
npm install
npm run build
```

### 3. (Optional) Seed Demo Data

To explore the dashboard with pre-populated dummy WhatsApp accounts, contacts, groups, and message templates:

```bash
composer run zeromsg:demo
```

### 4. Start Development Servers

Run the backend server, queue listener, log tailing, and Vite dev server simultaneously:

```bash
composer run dev
```

The application will be accessible at: **[http://localhost:8000](http://localhost:8000)** (or your configured `APP_URL`).

---

## ⚙️ WhatsApp Cloud API Configuration

To enable real-time messaging, webhooks, and template synchronization with Meta WhatsApp Cloud API:

1. Create an app in the [Meta for Developers Console](https://developers.facebook.com/).
2. Add the **WhatsApp** product to your app.
3. Obtain your credentials and update your `.env` file:

```env
WHATSAPP_APP_ID="your-whatsapp-app-id"
WHATSAPP_APP_SECRET="your-whatsapp-app-secret"
WHATSAPP_CONFIG_ID="your-whatsapp-config-id"

FACEBOOK_APP_ID="your-facebook-app-id"
FACEBOOK_APP_SECRET="your-facebook-app-secret"
```

4. Configure Webhooks in the Meta Developer Portal:
   - **Callback URL**: `https://your-domain.com/webhook/whatsapp`
   - **Verify Token**: Set in your tenant/account settings in the ZeroMsg dashboard.
   - **Subscribed Fields**: `messages`, `message_template_status_update`.

---

## 🔌 Developer REST API Reference

ZeroMsg provides a Developer API for programmatically sending WhatsApp messages.

### Authentication
Include your ZeroMsg API Key in the HTTP headers:
```http
Authorization: Bearer YOUR_API_KEY
```

### 1. Send Text Message
`POST /api/v1/messages/send-text`

```json
{
  "to": "+1234567890",
  "message": "Hello from ZeroMsg developer API!"
}
```

### 2. Send WhatsApp Template Message
`POST /api/v1/messages/send-template`

```json
{
  "to": "+1234567890",
  "template_name": "welcome_message",
  "language": "en_US",
  "components": []
}
```

---

## 🧪 Testing & Code Quality

ZeroMsg follows strict code quality and automated testing standards.

```bash
# Run Pest test suite
composer run test

# Check PHP code style (Laravel Pint)
npm run lint:check

# Format PHP code
npm run lint

# Check TypeScript types
npm run types:check

# Check ESLint & Prettier
npm run format:check
```

---

## 📜 Repository Structure

```
zeromsg/
├── app/
│   ├── Http/Controllers/     # Client, API & Webhook controllers
│   ├── Models/               # Eloquent Models (Tenant, Campaign, Contact, etc.)
│   └── Services/             # WhatsApp Cloud API integration service logic
├── config/                   # Laravel application configuration
├── database/                 # Migrations, seeders, and factories
├── resources/
│   ├── js/
│   │   ├── components/       # Radix & custom UI components
│   │   ├── pages/            # Inertia.js React views (Inbox, Campaigns, Flow Builder)
│   │   └── types/            # TypeScript interface definitions
│   └── css/                  # Tailwind CSS v4 setup
├── routes/
│   ├── api.php               # REST API endpoints
│   ├── web.php               # Dashboard routes
│   └── settings.php          # Auth & user settings routes
└── tests/                    # Pest PHP unit & feature tests
```

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
