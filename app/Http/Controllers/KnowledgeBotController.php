<?php

namespace App\Http\Controllers;

use App\Models\DocumentChunk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KnowledgeBotController extends Controller
{
    public function ask(Request $request)
    {
        $query = $request->input('query');

        if (empty($query)) {
            return response()->json(['error' => 'Missing query parameter.'], 400);
        }

        $ollamaUrl = env('OLLAMA_URL', 'http://host.docker.internal:11434');

        // 🔥 Step 1: Generate Query Embedding
        $embeddingResponse = Http::timeout(30)->post($ollamaUrl . '/api/embed', [
            'model' => 'mxbai-embed-large:latest',
            'input' => $query,
        ]);

        $queryEmbedding = $embeddingResponse->json()['embeddings'][0] ?? [];

        if (empty($queryEmbedding)) {
            return response()->json(['error' => 'Failed to generate query embedding.'], 500);
        }

        // 🔥 Step 2: Search
        $chunks = DocumentChunk::query()
            ->whereNotNull('embedding')
            ->whereVectorSimilarTo('embedding', $queryEmbedding, minSimilarity: 0.5)
            ->limit(3)
            ->get();

        if ($chunks->isEmpty()) {
            return response()->json([
                'answer' => "I don't know. No relevant documents found.",
                'citations' => []
            ]);
        }

        // 🔥 Step 3: Build Context
        $context = $chunks->map(fn($c) => $c->content)->implode("\n\n---\n\n");
        $citationIds = $chunks->pluck('id')->toArray();

        // 🔥 Step 4: Build Prompt
        $prompt = "You are a helpful assistant. Answer based ONLY on the provided context.\n\n";
        $prompt .= "Context:\n\"\"\"\n{$context}\n\"\"\"\n\n";
        $prompt .= "Question: {$query}\n\n";
        $prompt .= "If the answer is not in the context, say \"I don't know.\"";

        // 🔥 Step 5: Generate Answer (Increased Timeout)
        try {
            $response = Http::timeout(120)->post($ollamaUrl . '/api/generate', [
                'model' => 'llama3.1',   // Or use 'phi3:mini' for speed
                'prompt' => $prompt,
                'stream' => false,
            ]);

            $answer = $response->json()['response'] ?? 'No response from AI.';

            Log::info('Answer generated successfully.', ['query' => $query]);

            return response()->json([
                'answer' => $answer,
                'citations' => $citationIds,
                'chunks' => $chunks->pluck('content'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating answer: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to generate answer: ' . $e->getMessage(),
                'citations' => $citationIds,
            ], 500);
        }
    }
}