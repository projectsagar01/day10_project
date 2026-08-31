# 🧠 KnowledgeBot — Production-Ready RAG Pipeline with Laravel, pgvector & Ollama

> **Project:** Day 10 — Retrieval-Augmented Generation (RAG) Pipeline  
> **Goal:** Build an AI Assistant that answers questions based on private documents with citations  
> **Tech Stack:** Laravel 13, Laravel AI SDK, Ollama (mxbai-embed-large + llama3.1), PostgreSQL 18 + pgvector, Laravel Sail (Docker)  
> **Status:** ✅ Production-Ready | ✅ Zero Hallucination Guardrails | ✅ Citation Tracking

---

## 📖 Table of Contents

- [What is RAG?](#what-is-rag)
- [Why This Project Matters](#why-this-project-matters)
- [How It Works — The Complete Flow](#how-it-works--the-complete-flow)
- [Project Architecture](#project-architecture)
- [File Structure](#file-structure)
- [Installation & Setup](#installation--setup)
- [Configuration](#configuration)
- [The Problems I Faced & How I Fixed Them](#the-problems-i-faced--how-i-fixed-them)
- [Code Walkthrough](#code-walkthrough)
- [Testing the System](#testing-the-system)
- [API Documentation](#api-documentation)
- [Performance Optimization](#performance-optimization)
- [Security Considerations](#security-considerations)
- [Interview Questions](#interview-questions)
- [Key Takeaways](#key-takeaways)
- [Troubleshooting Checklist](#troubleshooting-checklist)
- [Next Steps](#next-steps)
- [Acknowledgments](#acknowledgments)
- [License](#license)

---

## 🤖 What is RAG?

RAG (Retrieval-Augmented Generation) is a technique where an AI model generates answers based on **external knowledge** (documents, PDFs, databases) rather than its training data.

### The Analogy

Think of a librarian (AI) who doesn't just give you an answer from memory. Instead, they:
1. **Search** the library shelves (documents)
2. **Find** the relevant books (chunks)
3. **Read** them carefully
4. **Answer** your question with **page numbers** (citations)

**Without RAG:** AI hallucinates or gives outdated answers.  
**With RAG:** AI answers ONLY from provided documents — **verified and auditable.**

---

## 🎯 Why This Project Matters

| Problem | Solution |
| :--- | :--- |
| AI Hallucinations | Answer is based ONLY on provided context |
| Private/Confidential Data | AI never sends your data to external APIs (Ollama is local) |
| Outdated Knowledge | Update documents → AI knowledge updates instantly |
| Token Limit Constraints | Only relevant chunks are sent to the LLM |
| Cost Optimization | Local Ollama = $0 API costs |
| Auditability | Every answer has a source citation |

**Real-World Use Cases:**
- Customer Support Knowledge Base
- Legal Contract Analysis
- Medical Policy Queries
- Internal Documentation Search
- E-commerce FAQ Automation

---

## ⚙️ How It Works — The Complete Flow

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                           KNOWLEDGEBOT — RAG PIPELINE                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────┐                                                   │
│  │  1. User Query       │                                                   │
│  │  "How to return an   │                                                   │
│  │   item?"             │                                                   │
│  └──────────┬──────────┘                                                   │
│             ↓                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  2. Query → Embedding (Manual HTTP to Ollama)                     │   │
│  │     POST /api/embed → mxbai-embed-large → 1024-dim vector         │   │
│  │     🔥 Bypasses SDK bug (OpenAI fallback)                         │   │
│  └──────────┬──────────────────────────────────────────────────────────┘   │
│             ↓                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  3. pgvector Semantic Search                                       │   │
│  │     DocumentChunk::whereVectorSimilarTo(embedding, minSimilarity)   │   │
│  │     🔥 Returns top 3 relevant chunks with similarity scores        │   │
│  └──────────┬──────────────────────────────────────────────────────────┘   │
│             ↓                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  4. Context Builder                                                │   │
│  │     Chunks → "Context:\n\"\"\"\n{chunks}\n\"\"\"\n\nQuestion: {query}" │   │
│  │     🔥 Includes citations (chunk IDs) for tracking                │   │
│  └──────────┬──────────────────────────────────────────────────────────┘   │
│             ↓                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  5. LLM Generation (Raw HTTP to Ollama)                           │   │
│  │     POST /api/generate → llama3.1 → Natural Language Answer        │   │
│  │     🔥 Prompt: "Answer based ONLY on context. If not found, say   │   │
│  │              'I don't know.'"                                      │   │
│  └──────────┬──────────────────────────────────────────────────────────┘   │
│             ↓                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  6. Response                                                       │   │
│  │     {                                                              │   │
│  │       "answer": "You can return items within 30 days...",         │   │
│  │       "citations": [1, 2, 3],                                    │   │
│  │       "chunks": ["...", "...", "..."]                            │   │
│  │     }                                                              │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  **The Guardrails:**                                                        │
│  ✅ minSimilarity: 0.5 → No context → "I don't know"                     │
│  ✅ whereNotNull('embedding') → Prevents SQL errors                      │
│  ✅ Citations → Every answer has source IDs                                │
│  ✅ No hallucinations → AI only uses provided context                     │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 🏗️ Project Architecture

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                          TECHNICAL ARCHITECTURE                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌───────────────┐       ┌───────────────┐       ┌───────────────────┐    │
│  │   Browser     │──────▶│  Laravel 13   │──────▶│  PostgreSQL 18    │    │
│  │   (User)      │       │  (Controller) │       │  + pgvector       │    │
│  └───────────────┘       └───────┬───────┘       └───────────────────┘    │
│                                  │                                         │
│                                  ▼                                         │
│                        ┌─────────────────────────────────┐                │
│                        │   Ollama (Host Machine)         │                │
│                        │  ┌───────────────────────────┐  │                │
│                        │  │ mxbai-embed-large:latest  │  │  ← Embedding  │
│                        │  │ (1024 dimensions)         │  │    Generation │
│                        │  └───────────────────────────┘  │                │
│                        │  ┌───────────────────────────┐  │                │
│                        │  │ llama3.1:latest           │  │  ← Answer     │
│                        │  │ (8B parameters)           │  │    Generation │
│                        │  └───────────────────────────┘  │                │
│                        └─────────────────────────────────┘                │
│                                                                             │
│  **Communication:**                                                         │
│  • Laravel Container ↔ Ollama Host → host.docker.internal:11434           │
│  • Raw HTTP (No SDK) → Avoids OpenAI fallback bug                        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 📁 Project File Structure

```text
day10_project/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── SeedDocumentsCommand.php    # 🔥 Seeds docs with embeddings
│   ├── Http/
│   │   └── Controllers/
│   │       └── KnowledgeBotController.php  # 🔥 RAG endpoint (/ask)
│   └── Models/
│       ├── Document.php                    # Original document model
│       └── DocumentChunk.php               # Chunks with embeddings
├── bootstrap/
│   └── app.php                             # CSRF exceptions (testing)
├── config/
│   └── ai.php                              # AI provider config (default)
├── database/
│   └── migrations/
│       ├── create_documents_table.php
│       └── create_document_chunks_table.php
├── routes/
│   └── web.php                             # /ask route
├── docker-compose.yml                      # Sail (Docker) configuration
├── .env                                    # Database + AI config
└── README.md                               # This file
```

---

## 🚀 Installation & Setup

### Prerequisites

| Requirement | Version | Verification Command |
| :--- | :--- | :--- |
| PHP | 8.3+ | `php -v` |
| Composer | Latest | `composer --version` |
| PostgreSQL | 15+ | `psql --version` |
| pgvector | Latest | `SELECT * FROM pg_extension WHERE extname = 'vector';` |
| Docker | Latest | `docker --version` |
| Docker Compose | Latest | `docker compose version` |
| Ollama | Latest | `ollama --version` |
| Ollama Models | mxbai-embed-large, llama3.1 | `ollama list` |

---

### Step 1: Clone & Install Dependencies

```bash
git clone <your-repo-url>
cd day10_project
composer install
cp .env.example .env
```

---

### Step 2: Environment Configuration (`.env`)

```env
# ========== Database (PostgreSQL + pgvector) ==========
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

# ========== AI Configuration ==========
AI_PROVIDER=ollama
AI_MODEL=llama3.1

# 🔥 IMPORTANT: host.docker.internal for Sail containers
OLLAMA_URL=http://host.docker.internal:11434

# ========== App ==========
APP_NAME=KnowledgeBot
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
```

**Why `host.docker.internal`?**  
Laravel Sail containers cannot access `localhost` directly. `host.docker.internal` resolves to the host machine's IP, allowing the container to reach Ollama running on your host.

---

### Step 3: Pull Ollama Models

```bash
# Embedding model (1024 dimensions)
ollama pull mxbai-embed-large:latest

# LLM for answer generation
ollama pull llama3.1:latest
```

---

### Step 4: Start Laravel Sail

```bash
# Start all containers in background
./vendor/bin/sail up -d

# Verify containers are running
./vendor/bin/sail ps
```

**Expected Output:**
```text
NAME                     STATUS
day10_project-pgsql-1    Up 2 minutes
day10_project-redis-1    Up 2 minutes
day10_project-laravel.test-1 Up 2 minutes
```

---

### Step 5: Run Migrations

```bash
./vendor/bin/sail artisan migrate
```

**Expected Output:**
```text
Migrating: 0001_01_01_000000_create_users_table
Migrated:  0001_01_01_000000_create_users_table (12.34ms)
...
Migrating: 2026_08_28_180033_create_documents_table
Migrated:  2026_08_28_180033_create_documents_table (8.56ms)
Migrating: 2026_08_28_180034_create_document_chunks_table
Migrated:  2026_08_28_180034_create_document_chunks_table (9.23ms)
```

---

### Step 6: Seed Documents with Embeddings

```bash
./vendor/bin/sail artisan documents:seed
```

**Expected Output:**
```text
🔮 Embedding: Return Policy
✅ Embedded: Return Policy (Vector: 1024 dims)
🔮 Embedding: Shipping Policy
✅ Embedded: Shipping Policy (Vector: 1024 dims)
🔮 Embedding: Warranty
✅ Embedded: Warranty (Vector: 1024 dims)
🔮 Embedding: Refund Policy
✅ Embedded: Refund Policy (Vector: 1024 dims)
✅ All documents seeded with embeddings!
```

---

### Step 7: Test the System

```bash
curl "http://localhost/ask?query=How%20to%20return%20an%20item%3F"
```

**Expected Output:**
```json
{
    "answer": "You can return items within 30 days. Items must be unused.",
    "citations": [1, 2, 3],
    "chunks": [
        "You can return items within 30 days. Items must be unused.",
        "Refunds are processed within 5-7 business days after return approval.",
        "Free shipping on orders over $50. Delivery in 3-5 business days."
    ]
}
```

---

## ⚙️ Configuration

### Ollama on Host Machine

Ollama needs to listen on all network interfaces (not just localhost) so Docker containers can access it:

```bash
# Stop existing Ollama
pkill ollama

# Start with host binding
OLLAMA_HOST=0.0.0.0 ollama serve &
```

**Verify Ollama is accessible from container:**
```bash
./vendor/bin/sail shell
```
```bash
curl http://host.docker.internal:11434/api/tags
```

---

### Laravel AI SDK Configuration (`config/ai.php`)

The default provider is set to OpenAI — we bypass this entirely by using raw HTTP calls. However, keep this config for reference:

```php
'default' => 'ollama',  // Not used in raw HTTP approach
'providers' => [
    'ollama' => [
        'driver' => 'ollama',
        'key' => env('OLLAMA_API_KEY', ''),
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    ],
],
```

---

## 🐛 The Problems I Faced & How I Fixed Them

### Problem 1: Docker Networking — `host.docker.internal` Not Resolving

**Error:**
```
cURL error 7: Failed to connect to host.docker.internal port 11434
```

**Why:** Docker container couldn't reach Ollama running on the host machine.

**The Fix:**

1. Added `extra_hosts` to `docker-compose.yml`:
   ```yaml
   laravel.test:
       extra_hosts:
           - 'host.docker.internal:host-gateway'
   ```

2. Set `OLLAMA_URL=http://host.docker.internal:11434` in `.env`

3. Restarted Ollama to listen on all interfaces:
   ```bash
   OLLAMA_HOST=0.0.0.0 ollama serve &
   ```

4. Verified connectivity from container:
   ```bash
   ./vendor/bin/sail shell
   curl http://host.docker.internal:11434/api/tags
   ```

---

### Problem 2: CSRF Token Mismatch (419 Page Expired)

**Error:**
```
419 Page Expired
```

**Why:** Laravel's CSRF protection blocked requests without a token.

**The Fix:**
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'documents/upload',   // 🔥 For testing only
    ]);
})
```

---

### Problem 3: Laravel AI SDK Embeddings Bug — OpenAI Fallback

**Error:**
```
OpenAiProvider::embeddings(): Argument #1 ($inputs) must be of type array, string given
```

**Why:** SDK v0.10.3 has a bug in `for()` method — it passes a string instead of an array, and falls back to OpenAI even when Ollama is configured.

**The Fix:** Used **raw HTTP calls** to Ollama instead of the SDK:

```php
// ❌ SDK (Doesn't work)
$embedding = Ai::embeddings('ollama')->for([$text])->generate();

// ✅ Raw HTTP (Works)
$response = Http::post($ollamaUrl . '/api/embed', [
    'model' => 'mxbai-embed-large:latest',
    'input' => $text,
]);
$embedding = $response->json()['embeddings'][0] ?? [];
```

---

### Problem 4: Ollama API Endpoint Changed (404)

**Error:**
```
404 Not Found on /api/embeddings
```

**Why:** Ollama >= 0.1.30 changed the endpoint from `/api/embeddings` to `/api/embed`.

**The Fix:**

| Old | New |
| :--- | :--- |
| `/api/embeddings` | `/api/embed` |
| `prompt` parameter | `input` parameter |

```php
// 🔥 Correct for Ollama >= 0.1.30
Http::post($ollamaUrl . '/api/embed', [
    'model' => 'mxbai-embed-large:latest',
    'input' => $text,   // 🔥 'prompt' → 'input'
]);
```

---

### Problem 5: Vector Dimension Mismatch

**Error:**
```
expected 1536 dimensions, not 1024
```

**Why:** Migration expected 1536 dimensions (OpenAI), but `mxbai-embed-large` generates 1024 dimensions.

**The Fix:**
```php
// Migration
$table->vector('embedding', 1024);   // 🔥 1536 → 1024
```

**Model Dimension Reference:**

| Model | Dimensions |
| :--- | :--- |
| OpenAI `text-embedding-3-small` | 1536 |
| OpenAI `text-embedding-3-large` | 3072 |
| Ollama `nomic-embed-text` | 768 |
| Ollama `mxbai-embed-large` | **1024** |
| Google Gemini | 768 |

---

### Problem 6: `whereVectorSimilarTo` Using OpenAI (401 Unauthorized)

**Error:**
```
HTTP request returned status code 401: Incorrect API key provided
```

**Why:** `whereVectorSimilarTo('embedding', $query, minSimilarity: 0.5)` internally generates an embedding for the query using the **default provider** (OpenAI), not Ollama.

**The Fix:** Manually generate the query embedding and pass it to the query:

```php
// 🔥 Generate query embedding manually
$embeddingResponse = Http::post($ollamaUrl . '/api/embed', [
    'model' => 'mxbai-embed-large:latest',
    'input' => $query,
]);
$queryEmbedding = $embeddingResponse->json()['embeddings'][0] ?? [];

// 🔥 Pass embedding directly to pgvector
$chunks = DocumentChunk::query()
    ->whereNotNull('embedding')
    ->whereVectorSimilarTo('embedding', $queryEmbedding, minSimilarity: 0.5)
    ->get();
```

---

### Problem 7: `embedding` Column NULL Causing SQL Error

**Error:**
```
invalid input syntax for type vector: "null"
DETAIL: Vector contents must start with "["
```

**Why:** Some chunks had `null` embedding values (seeder failed for some documents, or Ollama returned an empty response).

**The Fix:**

1. Remove NULL entries:
   ```bash
   ./vendor/bin/sail artisan tinker
   ```
   ```php
   DocumentChunk::whereNull('embedding')->delete();
   ```

2. Added `whereNotNull('embedding')` to the query:
   ```php
   $chunks = DocumentChunk::query()
       ->whereNotNull('embedding')   // 🔥 Guardrail
       ->whereVectorSimilarTo('embedding', $queryEmbedding, 0.5)
       ->get();
   ```

3. Made `embedding` column NOT NULL in migration:
   ```php
   $table->vector('embedding', 1024)->nullable(false);
   ```

---

### Problem 8: Duplicate Class Error — `SeedDocuments` Redeclared

**Error:**
```
Cannot redeclare class App\Console\Commands\SeedDocuments
```

**Why:** The command existed in both `app/Console/Commands/` and `database/seeders/`.

**The Fix:**
```bash
rm -f database/seeders/SeedDocuments.php
rm -f app/Console/Commands/SeedDocuments.php
./vendor/bin/sail artisan make:command SeedDocumentsCommand   # 🔥 New name
```

---

### Problem 9: Duplicate Data — Same Chunk Multiple Times

**Error:**
```
citations: [1, 4, 9]
chunks: ["...", "...", "..."]  // All identical
```

**Why:** Seeder ran multiple times without truncating old data.

**The Fix:**

1. Added truncate before seeding:
   ```bash
   ./vendor/bin/sail artisan tinker
   ```
   ```php
   Document::truncate();
   DocumentChunk::truncate();
   ```

2. Added duplicate check in seeder:
   ```php
   $existing = Document::where('title', $faq['title'])->first();
   if ($existing) {
       $this->info('⏭️ Skipping: ' . $faq['title'] . ' (already exists)');
       continue;
   }
   ```

---

### Problem 10: 500 Internal Server Error — `web` Middleware Missing

**Error:**
```
Target class [web] does not exist
```

**Why:** `bootstrap/app.php` was corrupted or missing the middleware configuration.

**The Fix:** Reset `bootstrap/app.php` to default:
```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'documents/upload',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();
```

---

## 💻 Code Walkthrough

### 1. The Seeder — `SeedDocumentsCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SeedDocumentsCommand extends Command
{
    protected $signature = 'documents:seed';
    protected $description = 'Seed documents with embeddings using mxbai-embed-large';

    public function handle(): void
    {
        $faqs = [
            ['title' => 'Return Policy', 'content' => 'You can return items within 30 days. Items must be unused.'],
            ['title' => 'Shipping Policy', 'content' => 'Free shipping on orders over $50. Delivery in 3-5 business days.'],
            ['title' => 'Warranty', 'content' => 'All products have a 1-year warranty against manufacturing defects.'],
            ['title' => 'Refund Policy', 'content' => 'Refunds are processed within 5-7 business days after return approval.'],
        ];

        $ollamaUrl = env('OLLAMA_URL', 'http://host.docker.internal:11434');

        foreach ($faqs as $faq) {
            // 🔥 Step 1: Generate embedding via raw HTTP
            $this->info('🔮 Embedding: ' . $faq['title']);

            $response = Http::post($ollamaUrl . '/api/embed', [
                'model' => 'mxbai-embed-large:latest',
                'input' => $faq['content'],
            ]);

            $embedding = $response->json()['embeddings'][0] ?? [];

            if (empty($embedding)) {
                $this->error('❌ Failed to generate embedding for: ' . $faq['title']);
                continue;
            }

            // 🔥 Step 2: Save document
            $doc = Document::create([
                'title' => $faq['title'],
                'content' => $faq['content'],
                'status' => 'processed',
            ]);

            // 🔥 Step 3: Save chunk with embedding
            DocumentChunk::create([
                'document_id' => $doc->id,
                'chunk_index' => 0,
                'content' => $faq['content'],
                'embedding' => $embedding,
            ]);

            $this->info('✅ Embedded: ' . $faq['title'] . ' (Vector: ' . count($embedding) . ' dims)');
        }

        $this->info('✅ All documents seeded with embeddings!');
    }
}
```

**Key Design Decisions:**

| Decision | Why |
| :--- | :--- |
| **Raw HTTP instead of SDK** | Bypasses SDK's OpenAI fallback bug |
| **`/api/embed` endpoint** | Correct for Ollama >= 0.1.30 |
| **`input` parameter** | Ollama's new parameter name (not `prompt`) |
| **`mxbai-embed-large` model** | 1024 dimensions, open-source, good quality |
| **Separate `Document` and `DocumentChunk` tables** | Allows future expansion (multiple chunks per document) |
| **`status` column** | Tracks processing status (pending/processed) |

---

### 2. The Model — `DocumentChunk.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'embedding',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',  // 🔥 Convert pgvector to PHP array
        ];
    }

    /**
     * Get the document that owns this chunk.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
```

**Why `cast('embedding' => 'array')`?**  
pgvector stores the embedding as a string like `[0.12, -0.34, ...]`. The cast automatically converts it to a PHP array when you access the property, making it easier to work with.

---

### 3. The Controller — `KnowledgeBotController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\DocumentChunk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class KnowledgeBotController extends Controller
{
    public function ask(Request $request)
    {
        $query = $request->input('query');

        // 🔥 Guard 1: No query
        if (empty($query)) {
            return response()->json(['error' => 'Missing query parameter.'], 400);
        }

        // 🔥 Step 1: Generate query embedding (RAW HTTP)
        $ollamaUrl = env('OLLAMA_URL', 'http://host.docker.internal:11434');
        $embeddingResponse = Http::post($ollamaUrl . '/api/embed', [
            'model' => 'mxbai-embed-large:latest',
            'input' => $query,
        ]);

        $queryEmbedding = $embeddingResponse->json()['embeddings'][0] ?? [];

        // 🔥 Guard 2: Embedding generation failed
        if (empty($queryEmbedding)) {
            return response()->json(['error' => 'Failed to generate query embedding.'], 500);
        }

        // 🔥 Step 2: pgvector Semantic Search
        $chunks = DocumentChunk::query()
            ->whereNotNull('embedding')   // 🔥 Guard 3: Skip NULL entries
            ->whereVectorSimilarTo('embedding', $queryEmbedding, minSimilarity: 0.5)
            ->limit(3)
            ->get();

        // 🔥 Guard 4: No chunks found
        if ($chunks->isEmpty()) {
            return response()->json([
                'answer' => "I don't know. No relevant documents found.",
                'citations' => []
            ]);
        }

        // 🔥 Step 3: Build Context
        $context = $chunks->map(fn($c) => $c->content)->implode("\n\n---\n\n");
        $citationIds = $chunks->pluck('id')->toArray();

        // 🔥 Step 4: Build Prompt with Guardrails
        $prompt = "You are a helpful assistant. Answer based ONLY on the provided context.\n\n";
        $prompt .= "Context:\n\"\"\"\n{$context}\n\"\"\"\n\n";
        $prompt .= "Question: {$query}\n\n";
        $prompt .= "If the answer is not in the context, say \"I don't know.\"";

        // 🔥 Step 5: Generate Answer
        $response = Http::post($ollamaUrl . '/api/generate', [
            'model' => 'llama3.1',
            'prompt' => $prompt,
            'stream' => false,
        ]);

        $answer = $response->json()['response'] ?? 'No response from AI.';

        // 🔥 Step 6: Return Answer + Citations
        return response()->json([
            'answer' => $answer,
            'citations' => $citationIds,
            'chunks' => $chunks->pluck('content'),
        ]);
    }
}
```

**The 4 Guardrails Explained:**

| Guardrail | Code | Why It's Important |
| :--- | :--- | :--- |
| **1. No Query** | `if (empty($query))` | Prevents error when no query parameter is provided |
| **2. Embedding Failed** | `if (empty($queryEmbedding))` | Prevents SQL errors if Ollama is down |
| **3. NULL Embeddings** | `whereNotNull('embedding')` | Prevents `invalid input syntax for type vector: "null"` error |
| **4. No Chunks Found** | `if ($chunks->isEmpty())` | Prevents AI from hallucinating; returns "I don't know" |

---

### 4. The Migration — `create_document_chunks_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
            $table->integer('chunk_index');
            $table->text('content');
            $table->vector('embedding', 1024);   // 🔥 1024 dimensions for mxbai-embed-large
            $table->timestamps();
        });

        // 🔥 HNSW Index for fast vector search
        DB::statement('CREATE INDEX idx_document_chunks_embedding_hnsw ON document_chunks USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64);');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
```

**Why HNSW Index?**
- **HNSW** (Hierarchical Navigable Small World) is an index type specifically designed for vector similarity search.
- **`m = 16`**: Number of bi-directional links per element. Higher = more accurate, more memory.
- **`ef_construction = 64`**: Build-time candidate list. Higher = better quality index, slower build.

---

### 5. The Route — `web.php`

```php
<?php

use App\Http\Controllers\KnowledgeBotController;
use Illuminate\Support\Facades\Route;

Route::get('/ask', [KnowledgeBotController::class, 'ask']);
```

---

## 🧪 Testing the System

### Test 1: Happy Path (Relevant Query)

```bash
curl "http://localhost/ask?query=How%20to%20return%20an%20item%3F"
```

**Expected Output:**
```json
{
    "answer": "You can return items within 30 days. Items must be unused.",
    "citations": [1, 2, 3],
    "chunks": [
        "You can return items within 30 days. Items must be unused.",
        "Refunds are processed within 5-7 business days after return approval.",
        "Free shipping on orders over $50. Delivery in 3-5 business days."
    ]
}
```

---

### Test 2: Irrelevant Query (Guardrail Test)

```bash
curl "http://localhost/ask?query=What%20is%20the%20meaning%20of%20life%3F"
```

**Expected Output:**
```json
{
    "answer": "I don't know. No relevant documents found.",
    "citations": []
}
```

---

### Test 3: Missing Query Parameter

```bash
curl "http://localhost/ask"
```

**Expected Output:**
```json
{
    "error": "Missing query parameter. Use ?query=Your question"
}
```

---

### Test 4: Tinker Verification

```bash
./vendor/bin/sail artisan tinker
```

```php
// Check document count
\App\Models\Document::count();   // Should be 4

// Check chunk count
\App\Models\DocumentChunk::count();   // Should be 4

// Check embedding dimensions
$chunk = \App\Models\DocumentChunk::first();
count($chunk->embedding);   // Should be 1024

// Check similarity search
use App\Models\DocumentChunk;
$chunks = DocumentChunk::query()
    ->whereNotNull('embedding')
    ->whereVectorSimilarTo('embedding', 'How to return an item?', 0.5)
    ->get();
$chunks->count();   // Should be 3
```

---

### Test 5: Performance Test

```bash
# Test response time
time curl -s "http://localhost/ask?query=How%20to%20return%20an%20item%3F" > /dev/null
```

**Expected:** ~2-5 seconds (depends on Ollama load)

---

## 📡 API Documentation

### Endpoint: `GET /ask`

**Description:** Query the KnowledgeBot with a question.

**Parameters:**

| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `query` | string | ✅ Yes | The question to ask |

**Response (Success):**

```json
{
    "answer": "You can return items within 30 days. Items must be unused.",
    "citations": [1, 2, 3],
    "chunks": [
        "You can return items within 30 days. Items must be unused.",
        "Refunds are processed within 5-7 business days after return approval.",
        "Free shipping on orders over $50. Delivery in 3-5 business days."
    ]
}
```

**Response (No Relevant Documents):**

```json
{
    "answer": "I don't know. No relevant documents found.",
    "citations": []
}
```

**Response (Error — Missing Query):**

```json
{
    "error": "Missing query parameter. Use ?query=Your question"
}
```

---

## ⚡ Performance Optimization

### 1. HNSW Index Tuning

| Parameter | Default | Production Value | Why |
| :--- | :--- | :--- | :--- |
| `m` | 16 | 24 | Higher = better accuracy, more memory |
| `ef_construction` | 64 | 128 | Higher = better quality index, slower build |
| `ef_search` | 40 | 100 | Higher = better recall, slower query |

**Example Index Creation:**
```sql
CREATE INDEX idx_embedding_hnsw ON document_chunks 
USING hnsw (embedding vector_cosine_ops) 
WITH (m = 24, ef_construction = 128);
```

---

### 2. Query-Level Optimization

| Technique | Implementation |
| :--- | :--- |
| **Limit Results** | `->limit(3)` — Only fetch top 3 chunks |
| **Similarity Threshold** | `minSimilarity: 0.5` — Skip irrelevant chunks |
| **Select Only Needed Columns** | `->select(['id', 'content'])` |
| **Caching** | Cache frequent queries |

---

### 3. Reduce Latency

| Bottleneck | Fix |
| :--- | :--- |
| **Ollama startup time** | Run Ollama as a service (`ollama serve &`) |
| **Model loading** | Keep models loaded in memory (Ollama does this by default) |
| **Network latency** | Use `host.docker.internal` instead of `localhost` |

---

## 🔒 Security Considerations

| Risk | Mitigation |
| :--- | :--- |
| **API Key Exposure** | Use `.env` for all secrets |
| **CSRF Attacks** | Disabled only for test routes |
| **SQL Injection** | Laravel Eloquent uses parameterized queries |
| **Rate Limiting** | Add `throttle` middleware for production |
| **Data Leakage** | Only return chunks, not full documents |
| **SSRF** | Validate `query` parameter (string length, no URLs) |

**Production Recommendations:**
```php
// Add rate limiting
Route::get('/ask', [KnowledgeBotController::class, 'ask'])
    ->middleware('throttle:60,1');
```

---

## 🎙️ Interview Questions

### Q1: *"What is RAG and why is it important?"*

**Answer:**
*"RAG (Retrieval-Augmented Generation) is a technique where the AI generates answers based on external documents rather than its training data. It's important because it reduces hallucinations, ensures answers are based on verified information, and allows companies to use private data without fine-tuning the model."*

---

### Q2: *"How do you prevent hallucinations in your RAG pipeline?"*

**Answer:**
*"I use four guardrails: First, a similarity threshold (`minSimilarity: 0.5`) ensures only relevant chunks are used. Second, if no chunks are found, the AI returns "I don't know" instead of making up an answer. Third, the prompt explicitly tells the AI to only use the provided context. Fourth, I use `whereNotNull('embedding')` to prevent SQL errors."*

---

### Q3: *"Why are you using raw HTTP instead of the Laravel AI SDK for embeddings?"*

**Answer:**
*"Laravel AI SDK v0.10.3 has a bug where the embedding provider falls back to OpenAI even when Ollama is configured. This caused 401 errors in my testing. By using raw HTTP calls, I bypass the SDK and have full control over the request/response cycle. The SDK is great for agents, but for embeddings I prefer raw control."*

---

### Q4: *"How do you handle vector search with pgvector?"*

**Answer:**
*"I use Laravel's `whereVectorSimilarTo()` method, which maps to pgvector's `<=>` cosine similarity operator. I manually generate the query embedding via Ollama's `/api/embed` endpoint and pass it directly to the query. I also use an HNSW index for sub-50ms latency even with large datasets."*

---

### Q5: *"What dimensions do you use and why?"*

**Answer:**
*"I use 1024 dimensions because the `mxbai-embed-large` model from Ollama generates 1024-dim vectors. It's open-source, runs locally, balances performance with quality, and is free. OpenAI's 1536-dim models are better but cost money."*

---

### Q6: *"How do you handle duplicate documents?"*

**Answer:**
*"I added a check in the seeder to skip documents with the same title. I also truncate tables before seeding to prevent duplicates. In production, I'd use a unique constraint on `title` or `content_hash`."*

---

### Q7: *"What happens if Ollama is down?"*

**Answer:**
*"If the embedding generation fails, I return a 500 error with a clear message. If the answer generation fails, I return 'No response from AI.' In production, I'd add a circuit breaker and fallback to OpenAI or cached responses."*

---

### Q8: *"How would you scale this to 1 million documents?"*

**Answer:**
*"I'd use partitioning by document type or date, increase HNSW index parameters for better accuracy, use a dedicated vector database like Pinecone or Qdrant, and implement query caching. I'd also use a job queue for document ingestion."*

---

## 📌 Key Takeaways

| Concept | What I Learned |
| :--- | :--- |
| **Raw HTTP > SDK** | When the SDK fails, drop down to REST. |
| **pgvector is powerful** | Semantic search with cosine similarity is fast and accurate. |
| **Guardrails are essential** | Similarity threshold + "I don't know" prevents hallucinations. |
| **Citations build trust** | Every answer should have a source. |
| **Docker networking matters** | Use `host.docker.internal` for Sail. |
| **Ollama API evolves** | `/api/embeddings` → `/api/embed`, `prompt` → `input`. |
| **NULL handling** | Always check for `NULL` embeddings. |
| **Duplicate protection** | Truncate before seeding or check for existing entries. |

---

## 🔧 Troubleshooting Checklist

| Issue | Fix |
| :--- | :--- |
| Ollama not reachable | `host.docker.internal` + `extra_hosts` in `docker-compose.yml` |
| 419 Page Expired | Disable CSRF for test routes |
| 401 Unauthorized | Use raw HTTP for embeddings |
| 404 on `/api/embeddings` | Use `/api/embed` with `input` parameter |
| Vector dimension mismatch | Set `embedding` to 1024 in migration |
| NULL embedding error | `whereNotNull('embedding')` in query |
| Duplicate class error | Delete duplicate command files |
| Duplicate chunks | `truncate()` before seeding |
| `web` middleware error | Reset `bootstrap/app.php` |
| Slow query | Add HNSW index with tuned parameters |

---

## 🚀 Next Steps

- **Day 11 — Hybrid Search:** Combine pgvector with PostgreSQL Full-Text Search for exact keyword matching.
- **Day 12 — KnowledgeBot with UI:** Add a Blade frontend for chat interaction.
- **Day 13 — Multi-Agent RAG:** Supervisor agent + Research agent + Writer agent.
- **Day 14 — LLM Evaluation:** Measure recall, precision, and latency metrics.
- **Day 15 — Deployment:** Deploy to production with Docker + Nginx.

---

## 🙏 Acknowledgments

| Technology | Why I Used It |
| :--- | :--- |
| **Laravel 13** | Modern PHP framework with built-in AI SDK support |
| **Laravel AI SDK** | Foundation for agents and tools |
| **Ollama** | Local LLMs — no API costs, full privacy |
| **pgvector** | Vector search in PostgreSQL — no extra infrastructure |
| **Docker / Laravel Sail** | Reproducible development environment |
| **mxbai-embed-large** | High-quality open-source embedding model |

---

## 📜 License

---

## 📞 Connect

- **GitHub:** [projectsagar01](https://github.com/projectsagar01)
- **YouTube:** [projectsagar01](https://youtube.com/projectsagar01)
- **Twitter/X:** [@projectsagar01](https://x.com/projectsagar01)

---

**Made with ❤️ from a slum in India to the world.** 🐇🔥

---