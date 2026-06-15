import sys
import opencc

def main():
    try:
        # Reconfigure standard I/O to UTF-8
        sys.stdin.reconfigure(encoding='utf-8')
        sys.stdout.reconfigure(encoding='utf-8')
        
        text = sys.stdin.read()
        
        # s2twp converts Simplified Chinese to Taiwan Traditional Chinese characters and phrases
        converter = opencc.OpenCC('s2twp')
        converted = converter.convert(text)
        
        sys.stdout.write(converted)
    except Exception as e:
        sys.stderr.write(str(e))
        sys.exit(1)

if __name__ == '__main__':
    main()
