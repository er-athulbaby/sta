<?php

namespace App\Filament\Portal\Pages;

use App\Exports\PeopleTemplateExport;
use App\Services\PeopleImporter;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportPeople extends Page
{
    protected static ?string $title = 'Import people from Excel';

    protected static ?string $navigationLabel = 'Excel import';

    public ?array $data = [];

    /** @var array<int, array<int, string>> */
    public array $rowErrors = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // storeFiles(false): state keeps the TemporaryUploadedFile, so no client-supplied path is ever trusted.
            FileUpload::make('file')->label('Excel file (.xlsx)')->required()->storeFiles(false)
                ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(5120),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Download the template, fill one row per athlete or team official, then upload it. Every row is checked first; nothing is saved unless all rows are valid. Photos, scans and clothing sizes are added afterwards on each person.'),
            Form::make([EmbeddedSchema::make('form')])->id('form'),
            View::make('filament.portal.pages.import-errors'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')->label('Download template')->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => (new PeopleTemplateExport)->download()),
            Action::make('import')->label('Validate and import')->action('import'),
        ];
    }

    public function import(PeopleImporter $importer): void
    {
        $file = $this->form->getState()['file'];
        $file = is_array($file) ? array_values($file)[0] : $file;
        if (! $file instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages(['data.file' => 'Please upload the Excel file again.']);
        }

        try {
            $result = $importer->import($file->getRealPath(), Filament::getTenant());
        } finally {
            $file->delete();
        }
        $this->rowErrors = $result->errors;

        $result->ok()
            ? Notification::make()->success()->title("{$result->created} people imported")->send()
            : Notification::make()->danger()->title('Nothing was imported. Fix the rows below and upload again.')->send();

        $this->form->fill();
    }
}
