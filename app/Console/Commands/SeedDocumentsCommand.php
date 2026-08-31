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
            $existing = Document::where('title', $faq['title'])->first();
    if ($existing) {
        $this->info('⏭️ Skipping: ' . $faq['title'] . ' (already exists)');
        continue;
    }
            $this->info('🔮 Embedding: ' . $faq['title']);

            // 🔥 CORRECT ENDPOINT + PARAMETER
            $response = Http::post($ollamaUrl . '/api/embed', [
                'model' => 'mxbai-embed-large:latest',
                'input' => $faq['content'],
            ]);

            $embedding = $response->json()['embeddings'][0] ?? [];

            if (empty($embedding)) {
                $this->error('❌ Failed to generate embedding for: ' . $faq['title']);
                continue;
            }

            $doc = Document::create([
                'title' => $faq['title'],
                'content' => $faq['content'],
                'status' => 'processed',
            ]);

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