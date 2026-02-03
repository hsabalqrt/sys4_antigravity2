<?php

namespace App\Providers\Filament;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\ClientDesignerNewResource;
use App\Filament\Resources\ClientDesignerResource;
use App\Filament\Resources\ClientNeedResource;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\CustodyResource;
use App\Filament\Resources\DesignerResource;
use App\Filament\Resources\IdeaResource;
use App\Filament\Resources\LocationResource;

use App\Filament\Resources\SocialMediaResource;
use App\Filament\Resources\TagGroupResource;
use App\Filament\Resources\TagResource;
use App\Filament\Resources\UsersResource;
use App\Filament\Resources\RoleResource;

use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\SubscriptionResource;
use App\Filament\Pages\AccountingDashboard;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;

use Filament\Navigation\NavigationItem;
use Filament\Enums\ThemeMode;
use Filament\Pages\Dashboard;

use App\Filament\Pages\DesignerDistribution;
use App\Filament\Pages\DesignerDashboard;
use App\Filament\Pages\ReviewerDashboard;

use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

use App\Filament\Auth\CustomLogin;


class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(CustomLogin::class)
            ->profile(isSimple: false)
            ->databaseNotifications()
            ->colors([
                // 'primary' => Color::Violet,
                'primary' => '#441188',
                'secondary' => '#ff6600',
                'warning' => '#ff6600',
            ])
            ->brandLogo(asset('images/true-nav.png'))
            ->brandLogoHeight('2.5rem')
            ->brandName('TrueERP')
            ->favicon(asset('images/true-nav.png'))
            ->darkMode(true)
            ->defaultThemeMode(ThemeMode::Dark)
            ->font('Cairo')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
                \App\Filament\Widgets\UserCountWidget::class, // Add this line
            ])
            ->plugins([
                FilamentFullCalendarPlugin::make()
                    ->schedulerLicenseKey('')
                    ->selectable()
                    ->editable()
                    ->timezone(config('app.timezone'))
                    ->locale(config('app.locale'))
                    ->plugins(['dayGrid'])
                    ->config([])
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // ->unsavedChangesAlerts()
            ->sidebarCollapsibleOnDesktop()
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder->groups([
                    NavigationGroup::make('')->items([
                        NavigationItem::make('الرئيسية')
                            ->icon('heroicon-o-home')
                            ->url(Dashboard::getUrl())
                            ->isActiveWhen(fn() => request()->routeIs('filament.admin.pages.dashboard')),

                        // ...DesignerDistribution::getNavigationItems(),


                    ]),
                    NavigationGroup::make('واجهات المراجع')
                        ->items([
                            ...(auth()->user()?->can('view_reviewer_dashboard') ? ReviewerDashboard::getNavigationItems() : []),
                        ]),
                    NavigationGroup::make('واجهات المصممين')
                        ->items([
                            ...(auth()->user()?->can('view_designer_dashboard') ? DesignerDashboard::getNavigationItems() : []),
                        ]),
                    NavigationGroup::make('التخطيط')
                        ->items([
                            ...(auth()->user()?->can('view_designer_distribution') ? DesignerDistribution::getNavigationItems() : []),
                        ]),
                    NavigationGroup::make('واجهة المشرفين')
                        ->items([
                            ...(auth()->user()?->can('view_supervisor_dashboard') || auth()->user()?->hasRole('supervisor') ? \App\Filament\Pages\SupervisorDashboard::getNavigationItems() : []),
                        ]),
                    NavigationGroup::make('CRM')
                        ->items([
                            ...(auth()->user()?->can('view_any_client') ? ClientResource::getNavigationItems() : []),
                            ...(auth()->user()?->can('view_any_social_media') ? SocialMediaResource::getNavigationItems() : []),
                            ...(auth()->user()?->can('view_any_location') ? LocationResource::getNavigationItems() : []),
                        ]),
                    NavigationGroup::make('المحتوى')
                        ->items([
                            ...(auth()->user()?->can('view_any_idea') ? IdeaResource::getNavigationItems() : []),
                            ...(auth()->user()?->can('view_any_tag_group') ? TagGroupResource::getNavigationItems() : []),
                            ...(auth()->user()?->can('view_any_category') ? CategoryResource::getNavigationItems() : []),
                            ...(auth()->user()?->can('view_any_client_need') ? ClientNeedResource::getNavigationItems() : []),
                            ...(auth()->user()?->can('view_any_tag') ? TagResource::getNavigationItems() : []),
                        ]),
                    NavigationGroup::make('المستخدمون')
                        ->items([
                            ...(auth()->user()?->can('view_any_users') ? UsersResource::getNavigationItems() : []),
                            ...RoleResource::getNavigationItems(),
                            ...(auth()->user()?->can('view_any_designer') ? DesignerResource::getNavigationItems() : []),
                            ...(auth()->user()?->can('view_any_custody') ? CustodyResource::getNavigationItems() : []),
                        ]),

                    NavigationGroup::make('الحسابات')
                        ->items([
                            // اذا المستخدم لديه دور المدير
                            ...(auth()->user()?->hasRole('admin') ? TransactionResource::getNavigationItems() : []),
                            ...(auth()->user()?->hasRole('admin') ? SubscriptionResource::getNavigationItems() : []),
                            ...(auth()->user()?->hasRole('admin') ? AccountingDashboard::getNavigationItems() : []),
                        ]),
                    NavigationGroup::make('الاعدادات')
                        ->items([
                            ...(auth()->user()?->can('view_any_currency') ? CurrencyResource::getNavigationItems() : []),
                        ]),
                ]);
            })
            // ->spa()
            ->viteTheme('resources/css/app.css');
    }
}
