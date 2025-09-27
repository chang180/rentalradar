/**
 * 瀏覽器相容的 EventEmitter 實作
 * 用於替代 Node.js 的 events 模組
 */
export class EventEmitter {
    private events: { [key: string]: Function[] } = {};

    /**
     * 添加事件監聽器
     */
    on(event: string, listener: Function): this {
        if (!this.events[event]) {
            this.events[event] = [];
        }
        this.events[event].push(listener);
        return this;
    }

    /**
     * 移除事件監聽器
     */
    off(event: string, listener: Function): this {
        if (!this.events[event]) {
            return this;
        }
        this.events[event] = this.events[event].filter((l) => l !== listener);
        return this;
    }

    /**
     * 添加一次性事件監聽器
     */
    once(event: string, listener: Function): this {
        const onceWrapper = (...args: any[]) => {
            this.off(event, onceWrapper);
            listener(...args);
        };
        return this.on(event, onceWrapper);
    }

    /**
     * 發送事件
     */
    emit(event: string, ...args: any[]): boolean {
        if (!this.events[event]) {
            return false;
        }
        this.events[event].forEach((listener) => {
            try {
                listener(...args);
            } catch (error) {
                console.error(`Error in event listener for ${event}:`, error);
            }
        });
        return true;
    }

    /**
     * 移除所有事件監聽器
     */
    removeAllListeners(event?: string): this {
        if (event) {
            delete this.events[event];
        } else {
            this.events = {};
        }
        return this;
    }

    /**
     * 獲取事件監聽器數量
     */
    listenerCount(event: string): number {
        return this.events[event] ? this.events[event].length : 0;
    }

    /**
     * 獲取所有事件名稱
     */
    eventNames(): string[] {
        return Object.keys(this.events);
    }
}
