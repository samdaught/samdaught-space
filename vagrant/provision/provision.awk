BEGIN {
    print "AWK BEGINs its work:"
    IGNORECASE = 1

    match(ip, /(([0-9]+\.)+)/, arr)
    ip = arr[1] "*"
}
{
    if (FILENAME != isFile["same"]){
        msg = "- Work with: " FILENAME
        close(isFile["same"])
        delete isFile
        isFile["same"] = FILENAME
        switch (FILENAME){
        case /config\/web\.php$/:
            isFile["IsConfWeb"] = 1
            msg = msg " - add allowed IP: " ip
            break
        }
        print msg
    }

    if (isFile["IsConfWeb"]){
        if (match($0, "allowedIPs") && !match($0, ip)){
            match($0, /([^\]]+)(.+)/, arr)
            $0 = sprintf("%s, '%s'%s", arr[1], ip, arr[2])
        }
        print $0 > FILENAME
    }
}
END {
    print "AWK ENDs its work."
}
