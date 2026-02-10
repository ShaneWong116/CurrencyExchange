<?php

namespace App\Filament\Forms\Components;

use App\Models\CommonNote;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

class CommonNotesField extends Field
{
    protected string $view = 'filament.forms.components.common-notes-field';
    
    /**
     * Common notes collection
     *
     * @var Collection|null
     */
    protected ?Collection $notes = null;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->afterStateHydrated(function (CommonNotesField $component, $state) {
            // Load common notes when the field is hydrated
            $component->loadCommonNotes();
        });
    }
    
    /**
     * Load common notes for the current user
     *
     * @return void
     */
    public function loadCommonNotes(): void
    {
        $user = auth()->user();
        
        if (!$user) {
            $this->notes = collect([]);
            return;
        }
        
        $this->notes = CommonNote::where('user_id', $user->id)
            ->where('user_type', 'admin')
            ->latest()
            ->get();
    }
    
    /**
     * Get the common notes collection
     *
     * @return Collection
     */
    public function getNotes(): Collection
    {
        if ($this->notes === null) {
            $this->loadCommonNotes();
        }
        
        return $this->notes ?? collect([]);
    }
}
