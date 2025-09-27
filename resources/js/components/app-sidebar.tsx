import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { LayoutGrid, BarChart3, Map, Home, ChevronLeft, ChevronRight } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: '首頁',
        href: dashboard(),
        icon: Home,
    },
    {
        title: '地圖',
        href: '/map',
        icon: Map,
    },
];

export function AppSidebar() {
    const { state, toggleSidebar } = useSidebar();
    const isCollapsed = state === 'collapsed';

    return (
        <div className="relative">
            <Sidebar collapsible="icon" variant="inset">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link href={dashboard()} prefetch>
                                    <AppLogo />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>

                <SidebarContent>
                    <NavMain items={mainNavItems} />
                </SidebarContent>

                <SidebarFooter>
                    <NavUser />
                </SidebarFooter>
            </Sidebar>
            
            {/* 側邊欄切換按鈕 - 位於側邊欄和內容區域的邊線上 */}
            <button
                onClick={toggleSidebar}
                className="absolute -right-3 top-6 z-10 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
                {isCollapsed ? (
                    <ChevronRight className="h-3 w-3 text-gray-600 dark:text-gray-400" />
                ) : (
                    <ChevronLeft className="h-3 w-3 text-gray-600 dark:text-gray-400" />
                )}
            </button>
        </div>
    );
}
