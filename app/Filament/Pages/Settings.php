<?php

namespace App\Filament\Pages;

use App\Enums\StaPermission;
use App\Filament\Concerns\RequiresStaPermission;
use App\Filament\Shared\AttachmentActions;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

class Settings extends Page
{
    protected static ?int $navigationSort = 9;

    use RequiresStaPermission;

    public ?array $data = [];

    protected static function requiredPermission(): StaPermission
    {
        return StaPermission::ManageMasterData;
    }

    public function mount(): void
    {
        $logo = Setting::get(Setting::NOC_LOGO);
        $this->form->fill([
            'noc_logo_path' => $logo ? [$logo] : [],
            'pdf_declaration' => Setting::get(Setting::DECLARATION, Setting::DEFAULT_DECLARATION),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            AttachmentActions::view(FileUpload::make('noc_logo_path')->label('NOC logo (printed on PDFs)')->image()->acceptedFileTypes(['image/jpeg', 'image/png'])->maxSize(2048)
                ->disk(config('filesystems.private'))->directory('settings')->visibility('private')
                // No record backs this page, so allow exactly the currently stored logo path.
                ->preventFilePathTampering(allowFilePathUsing: fn (string $file) => $file === Setting::get(Setting::NOC_LOGO))),
            Textarea::make('pdf_declaration')->label('Declaration printed above signatures')->required()->rows(4),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save')->submit('save')->keyBindings(['mod+s'])])]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $logo = $state['noc_logo_path'] ?? null;
        Setting::put(Setting::NOC_LOGO, is_array($logo) ? (array_values($logo)[0] ?? null) : $logo);
        Setting::put(Setting::DECLARATION, $state['pdf_declaration']);
        Notification::make()->success()->title('Settings saved')->send();
    }
}
