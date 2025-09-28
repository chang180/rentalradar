import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { 
    Users, 
    Upload, 
    Calendar, 
    Shield,
    FileText,
    Settings,
    BarChart3
} from 'lucide-react';

const adminNavItems: NavItem[] = [
    {
        title: '使用者管理',
        href: '/admin/users',
        icon: Users,
    },
    {
        title: '檔案上傳',
        href: '/admin/uploads',
        icon: Upload,
    },
    {
        title: '排程管理',
        href: '/admin/schedules',
        icon: Calendar,
    },
    {
        title: '效能監控',
        href: '/admin/performance',
        icon: BarChart3,
    },
];

export function AdminNav() {
    const page = usePage();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>管理員功能</SidebarGroupLabel>
            <SidebarMenu>
                {adminNavItems.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={
                                !item.external &&
                                page.url.startsWith(
                                    typeof item.href === 'string'
                                        ? item.href
                                        : item.href.url,
                                )
                            }
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href}>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
