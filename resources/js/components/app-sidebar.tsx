import { AdminNav } from '@/components/admin-nav';
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
import { useAdminCheck } from '@/hooks/useAdmin';
import { dashboard } from '@/routes/index';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BarChart3, ChevronLeft, ChevronRight, Home, Map } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: '首頁',
        href: dashboard.url(),
        icon: Home,
    },
    {
        title: '地圖',
        href: '/map',
        icon: Map,
    },
    {
        title: '市場分析',
        href: '/analysis',
        icon: BarChart3,
    },
];

export function AppSidebar() {
    const { state, toggleSidebar } = useSidebar();
    const isCollapsed = state === 'collapsed';
    const isAdmin = useAdminCheck();

    return (
        <div className="relative">
            <Sidebar collapsible="icon" variant="inset">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link href={dashboard.url()} prefetch>
                                    <AppLogo />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>

                <SidebarContent>
                    <NavMain items={mainNavItems} />
                    {isAdmin && <AdminNav />}
                </SidebarContent>

                <SidebarFooter>
                    <NavUser />
                </SidebarFooter>
            </Sidebar>

            {/* 側邊欄切換按鈕 - 位於側邊欄和內容區域的邊線上 */}
            <button
                onClick={toggleSidebar}
                className="absolute top-6 -right-3 z-10 flex h-6 w-6 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
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
