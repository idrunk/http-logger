const host: string = 'https://logger.drunkce.com';

enum Method {
    Append,
    Prepend,
    Delete,
    Export,
}

/**
 * HTTP记录器调试工具，方便不便通过开发者工具调试的环境调试
 */
export default class HttpLogger {
    private static instance: HttpLogger;
    private static batchWaiting: number = 500;

    private readonly host: string;
    private delayId: number;
    private lastResolve: Function;
    private contents: {[index: string]: string[]} = {};

    constructor(host: string) {
        this.host = host;
    }

    private async fetch(method: Method, path: string, data?: string, logClient: boolean = false): Promise<any> {
        const fullPath: string = this.host + (method === Method.Export ? '/_' : '') + path;
        const httpMethod = method === Method.Delete ? 'DELETE' : (method === Method.Export ? 'GET' : 'POST');
        const headers = {};
        ! logClient && (headers['Dce-Debug'] = '1');
        method === Method.Prepend && (headers['Log-Type'] = 'prepend');
        return (await fetch(fullPath, {mode: 'cors', method: httpMethod, body: data + (logClient ? '' : '\n\n'), headers})).text();
    }

    /**
     * 批量追加（很多情况下我们需在循环中调试，循环中向服务器发送请求时，无法保证服务器会按序收到或处理这些请求，所以设计了此延迟批量追加机制，将短时间内的多个追加合并到一起发送）
     * @param method
     * @param waiting
     * @param logClient
     * @param isReplace
     * @private
     */
    private async batchFetch(method: Method, waiting: number, logClient: boolean = false, isReplace: boolean = false): Promise<string> {
        return new Promise(resolve => {
            if (this.delayId) {
                window.clearTimeout(this.delayId);
                this.delayId = 0;
                this.lastResolve(null);
            }
            this.lastResolve = resolve;
            this.delayId = window.setTimeout(async () => {
                isReplace && await Promise.all(Object.keys(this.contents).map(path => this.fetch(Method.Delete, path))); // replace操作则先删掉
                Promise.all(Object.entries(this.contents).map(([path, contents]) =>
                    this.fetch(method, path, (method === Method.Prepend ? contents.reverse() : contents).join('\n\n'), logClient))).then(rs=>resolve(rs.join(',')));
                this.delayId = 0;
                this.lastResolve = null;
                this.contents = {};
            }, waiting);
        });
    }

    private async post(method: Method, path: string, data: any[], logClient: boolean = false, isReplace: boolean = false): Promise<any> {
        ! path.startsWith('/') && (path = '/' + path);
        ! this.contents[path] && (this.contents[path] = []);
        this.contents[path].push(data.map(d => typeof d === 'string' ? d : JSON.stringify(d)).join('\n'));
        return this.batchFetch(method, HttpLogger.batchWaiting, logClient, isReplace);
    }

    private static get inst(): HttpLogger {
        if (! this.instance) this.instance = new HttpLogger(host);
        return this.instance;
    }

    /**
     * 向服务器文件追加内容
     * @param path
     * @param data
     */
    public static async append(path: string, ... data: any): Promise<string> {
        return await this.inst.post(Method.Append, path, data);
    }

    /**
     * 向服务器文件前置追加内容
     * @param path
     * @param data
     */
    public static async prepend(path: string, ... data: any): Promise<string> {
        return await this.inst.post(Method.Prepend, path, data);
    }

    /**
     * 向服务器文件写入内容（先删除再追加）
     * @param path
     * @param data
     */
    public static async replace(path: string, ... data: any): Promise<string> {
        return await this.inst.post(Method.Append, path, data, false, true);
    }

    /**
     * 向服务器文件追加内容，包括客户端信息
     * @param path
     * @param data
     */
    public static async appendClient(path: string, ... data: any): Promise<string> {
        return await this.inst.post(Method.Append, path, data, true);
    }

    /**
     * 向服务器文件前置追加内容，包括客户端信息
     * @param path
     * @param data
     */
    public static async prependClient(path: string, ... data: any): Promise<string> {
        return await this.inst.post(Method.Prepend, path, data, true);
    }

    /**
     * 向服务器文件写入内容（先删除再追加），包括客户端信息
     * @param path
     * @param data
     */
    public static async replaceClient(path: string, ... data: any): Promise<string> {
        return await this.inst.post(Method.Append, path, data, true, true);
    }

    /**
     * 删除服务器文件
     * @param path
     */
    public static async delete(path: string): Promise<boolean> {
        ! path.startsWith('/') && (path = '/' + path);
        return await this.inst.fetch(Method.Delete, path);
    }

    /**
     * 服务器文件导出
     * @param path
     */
    public static async export(path: string): Promise<string> {
        ! path.startsWith('/') && (path = '/' + path);
        const content = await this.inst.fetch(Method.Export, path);
        // could download and export
        return content;
    }
}