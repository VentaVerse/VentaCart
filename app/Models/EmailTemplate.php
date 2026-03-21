<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['store_id', 'slug', 'name', 'subject', 'body', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function renderSubject(array $data): string
    {
        return $this->replacePlaceholders($this->subject, $data);
    }

    public function renderBody(array $data): string
    {
        return $this->replacePlaceholders($this->body, $data);
    }

    private function replacePlaceholders(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }
        return $text;
    }
}
