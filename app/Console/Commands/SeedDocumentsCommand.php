<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SeedDocumentsCommand extends Command
{
    protected $signature = 'documents:seed';
    protected $description = 'Seed documents with embeddings using msbai-embed-large';

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
            $this->info('🔮 Embedding: ' . $faq['title']);

            $response = Http::post($ollamaUrl . '/api/embeddings', [
                'model' => 'msbai-embed-large:latest',
                'prompt' => $faq['content'],
            ]);

            $embedding = $response->json()['embedding'] ?? [];

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