<?php

namespace App\Filament\Shared;

use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;

/** The photo and mobile of a user account (STA and association users), on the profile pages and the user forms and lists. */
final class UserFields
{
    /** JPG/PNG up to 2 MB on the private disk (user-photos/), drawn like the person photo field, with Remove. */
    public static function photo(): FileUpload
    {
        return AttachmentActions::view(FileUpload::make('photo_path')->label('Photo')
            ->view('filament.people.upload-field', ['variant' => 'photo', 'removable' => true, 'uploadLabel' => 'Upload photo'])
            ->acceptedFileTypes(['image/jpeg', 'image/png'])->maxSize(2048)
            ->disk(config('filesystems.private'))->directory('user-photos')->visibility('private'));
    }

    public static function mobile(): Grid
    {
        return PhoneField::make('phone', 'Mobile', codeLabel: 'Mobile country code');
    }

    /** The photo, or the same initials circle as the sidebar. */
    public static function avatarColumn(): ImageColumn
    {
        return ImageColumn::make('avatar')->label('')->circular()->imageSize(32)
            ->state(fn (User $record) => filament()->getUserAvatarUrl($record));
    }

    public static function mobileColumn(): TextColumn
    {
        return TextColumn::make('mobile')->label('Mobile')->state(fn (User $record) => $record->mobile())->placeholder('—');
    }
}
