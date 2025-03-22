<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\Sucursal;
use App\Models\User;
use Filament\Facades\Filament; # Se agrega para obtener solo los roles del tenant actual
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput; # Agregar si es un Input [Form]
use Filament\Tables\Columns\TextColumn; # Agregar si es un Column [Table]
use Filament\Forms\Components\Select; # Agregar si es un Select [Form]
use Illuminate\Database\Eloquent\Model; # Se agrega para el Select [Form]

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name'),
                TextInput::make('email'),
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->hiddenOn('edit'),
                Select::make('roles')
                    ->relationship('roles', 'name', modifyQueryUsing: fn (Builder $query) => $query->whereBelongsTo(Filament::getTenant()))
                    ->saveRelationshipsUsing(function (Model $record, $state) {
                         $record->roles()->syncWithPivotValues($state, [config('permission.column_names.team_foreign_key') => getPermissionsTeamId()]);
                    })
                   ->multiple()
                   ->preload()
                   ->searchable(),

                // Muestra el campo solo si hay más de una sucursal en la organización
                Select::make('sucursales')
                   ->label('Sucursales')
                   ->relationship('sucursales', 'nombre', modifyQueryUsing: fn (Builder $query) => $query->whereBelongsTo(Filament::getTenant()) ) // Asegúrate de que la relación en User.php esté bien definida
                   ->multiple()
                   ->preload()
                   ->searchable()
                   ->required()
                   ->visible(function () {
                            $tenant = Filament::getTenant();
                            return Sucursal::where('organizacion_id', $tenant->id)->count() > 1;
                        }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc') # Ordenar por fecha de creación
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
                TextColumn::make('roles.name'),
                TextColumn::make('sucursales.nombre'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
