<?php
/**
 * typecho 文章内容自动链接标签
 *
 * @package AutoLinkTags
 * @author 地主非
 * @version 20231124
 * @link http://www.myhelen.cn
 */
class AutoLinkTags_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        // 注册文章输出过滤器
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('AutoLinkTags_Plugin', 'autoLink');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('AutoLinkTags_Plugin', 'autoLink');
        
        // 检查静态文件是否存在，如果不存在则更新缓存
        $cacheFile = __DIR__ . '/tags_cache.txt';
        if (!file_exists($cacheFile) || time() >= filemtime($cacheFile) + 86400) { // 24小时更新一次
            self::updateTagsCache($cacheFile);
        }
        
        return '插件启用成功';
    }

    public static function deactivate()
    {
        return '插件禁用成功';
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }
    
    /**
     * 更新标签数据缓存
     * @param string $cacheFile 缓存文件路径
     */
    private static function updateTagsCache($cacheFile)
    {
        // 获取系统中所有标签
        $tags = Typecho_Widget::widget('Widget_Metas_Tag_Cloud');
        
        // 将标签数据转换为数组
        $tagArr = [];
        while ($tags->next()) {
            $tagArr[$tags->name] = $tags->permalink;
        }
        
        // 将标签数据保存到缓存文件
        file_put_contents($cacheFile, serialize($tagArr));
    }
    
    /**
     * 自动将内容中的标签名转换为标签页链接
     * @param string $content 文章或摘要的内容
     * @return string 修改后的内容
     */
    public static function autoLink($content, $widget, $lastResult)
    {
        // 获取标签数据缓存
        $cacheFile = __DIR__ . '/tags_cache.txt';
        if (file_exists($cacheFile)) {
            $tagArr = unserialize(file_get_contents($cacheFile));
        } else {
            return $content;
        }
        
        // 处理每个标签
        foreach ($tagArr as $name => $permalink) {
            // 判断标签是否已经被链接过（避免重复链接）
            if (strpos($content, '<a href="' . $permalink . '" target="_blank">' . $name . '</a>') !== false) {
                continue;
            }
            
            // 使用 DOM 解析器处理 HTML 内容
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="UTF-8">' . $content);
            libxml_clear_errors();
            
            // 查询包含标签的节点
            $xpath = new DOMXPath($doc);
            $nodes = $xpath->query('//text()[not(ancestor::a)]'); // 排除已经在链接内的文本节点
            
            // 处理每个节点
            foreach ($nodes as $node) {
                // 获取节点的文本内容
                $text = $node->textContent;
                
                // 判断文本节点是否包含标签名称
                if (strpos($text, $name) !== false) {
                    // 替换标签为链接
                    $newContent = str_replace($name, '<a href="' . $permalink . '" target="_blank">' . $name . '</a>', $node->ownerDocument->saveHTML($node));
                    
                    // 替换原内容为新内容（仅替换第一个匹配）
                    $content = str_replace($node->ownerDocument->saveHTML($node), $newContent, $content);
                }
            }
        }
        
        return $content;
    }
}
