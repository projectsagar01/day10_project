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

        if (empty($query)) {
            return response()->json(['error' => 'Missing query parameter.'], 400);
        }

        // 🔥 Step 1: Generate query embedding manually (via Ollama)
        $ollamaUrl = env('OLLAMA_URL', 'http://host.docker.internal:11434');
        $embeddingResponse = Http::post($ollamaUrl . '/api/embed', [
            'model' => 'mxbai-embed-large:latest',
            'input' => $query,
        ]);

        $queryEmbedding = $embeddingResponse->json()['embeddings'][0] ?? [];

        if (empty($queryEmbedding)) {
            return response()->json(['error' => 'Failed to generate query embedding.'], 500);
        }

        // 🔥 Step 2: Search with the embedding
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

        // 🔥 Step 5: Generate Answer via Ollama
        $response = Http::post($ollamaUrl . '/api/generate', [
            'model' => 'llama3.1',
            'prompt' => $prompt,
            'stream' => false,
        ]);

        $answer = $response->json()['response'] ?? 'No response from AI.';

        return response()->json([
            'answer' => $answer,
            'citations' => $citationIds,
            'chunks' => $chunks->pluck('content'),
        ]);
    }
}